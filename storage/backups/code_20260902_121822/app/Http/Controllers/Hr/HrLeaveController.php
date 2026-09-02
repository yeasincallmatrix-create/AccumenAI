<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\HrEmployee;
use App\Models\HrLeaveApplication;
use App\Models\HrLeaveBalance;
use App\Models\HrLeaveType;
use App\Services\HrLeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrLeaveController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrLeaveService $leaveService) {}

    public function dashboard(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $pending = HrLeaveApplication::query()->where('institute_id', $institute->id)->where('status', 'pending')->count();
        $approved = HrLeaveApplication::query()->where('institute_id', $institute->id)->where('status', 'approved')->count();
        $types = HrLeaveType::query()->count();
        $balances = HrLeaveBalance::query()->where('institute_id', $institute->id)->count();

        return view('hr.leave.dashboard', [
            'institute' => $institute,
            'stats' => compact('pending', 'approved', 'types', 'balances'),
            'recent' => HrLeaveApplication::query()->with(['employee', 'leaveType'])->latest('id')->limit(5)->get(),
        ]);
    }

    public function types(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $types = HrLeaveType::query()->orderBy('display_order')->orderBy('name')->paginate(20)->withQueryString();

        return view('hr.leave.types', ['institute' => $institute, 'types' => $types]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:40'],
            'yearly_allowance' => ['required', 'integer', 'min:0', 'max:365'],
            'carry_forward' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $this->leaveService->createType($data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Leave type created.');
    }

    public function updateType(Request $request, HrLeaveType $leaveType): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:40'],
            'yearly_allowance' => ['nullable', 'integer', 'min:0', 'max:365'],
            'carry_forward' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $this->leaveService->updateType($leaveType, $data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Leave type updated.');
    }

    public function balances(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $query = HrLeaveBalance::query()->with(['employee', 'leaveType']);
        if ($this->actingBranchId($request)) {
            $query->whereHas('employee', fn ($q) => $q->where('branch_id', $this->actingBranchId($request)));
        }
        if (filled($request->query('employee_id'))) {
            $query->where('employee_id', (int) $request->query('employee_id'));
        }
        if (filled($request->query('year'))) {
            $query->where('year', (int) $request->query('year'));
        }
        $balances = $query->orderByDesc('year')->paginate(20)->withQueryString();

        return view('hr.leave.balances', [
            'institute' => $institute, 'balances' => $balances,
            'employees' => $this->employeeOptions($institute->id),
            'types' => HrLeaveType::query()->where('is_active', true)->get(),
        ]);
    }

    public function applications(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $query = HrLeaveApplication::query()->with(['employee', 'leaveType'])->where('institute_id', $institute->id);
        if ($this->actingBranchId($request)) {
            $query->whereHas('employee', fn ($q) => $q->where('branch_id', $this->actingBranchId($request)));
        }
        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }
        if (filled($request->query('employee_id'))) {
            $query->where('employee_id', (int) $request->query('employee_id'));
        }
        if (filled($request->query('leave_type_id'))) {
            $query->where('leave_type_id', (int) $request->query('leave_type_id'));
        }
        $apps = $query->latest('id')->paginate(20)->withQueryString();

        return view('hr.leave.applications', [
            'institute' => $institute, 'applications' => $apps,
            'employees' => $this->employeeOptions($institute->id),
            'types' => HrLeaveType::query()->where('is_active', true)->get(),
            'statuses' => HrLeaveApplication::STATUSES,
        ]);
    }

    public function createApplication(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('hr.leave.form', [
            'institute' => $institute,
            'employees' => $this->employeeOptions($institute->id),
            'types' => HrLeaveType::query()->where('is_active', true)->ordered()->get(),
        ]);
    }

    public function storeApplication(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:hr_leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);
        if ($request->hasFile('attachment')) {
            $data['attachment_file'] = $request->file('attachment');
        }

        $app = $this->leaveService->apply($data, $institute->id, $this->actorId($request));

        return redirect()->route('hr.leave.applications')->with('status', 'Leave applied ('.$app->status.').');
    }

    public function decide(Request $request, HrLeaveApplication $application): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,cancelled'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->leaveService->decide($application, $data['decision'], $data['rejection_reason'] ?? null, $institute->id, $this->actorId($request));

        return back()->with('status', 'Leave '.$data['decision'].'.');
    }

    private function employeeOptions(int $instituteId)
    {
        return HrEmployee::query()->where('institute_id', $instituteId)->orderBy('display_name')->limit(200)->get(['id', 'display_name', 'employee_code', 'branch_id']);
    }
}
