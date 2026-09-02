<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\HrEmploymentHistory;
use App\Models\HrEmploymentPeriod;
use App\Services\HrEmployeeService;
use App\Services\HrEmploymentLifecycleService;
use App\Services\ProfileImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Employee Core + Employment Lifecycle (HR-1 + HR-2).
 *
 * Tenant/branch isolation: never trusts ids from input; uses ResolvesInstitute + BranchContext global scopes.
 */
class HrEmployeeController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly HrEmployeeService $employeeService,
        private readonly HrEmploymentLifecycleService $lifecycle,
        private readonly ProfileImageService $profileImage,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = HrEmployee::query()->with(['branch', 'department', 'designation', 'reportingManager']);

        if (filled($q = trim((string) $request->query('q')))) {
            $query->search($q);
        }
        if (filled($request->query('department_id'))) {
            $query->where('department_id', (int) $request->query('department_id'));
        }
        if (filled($request->query('designation_id'))) {
            $query->where('designation_id', (int) $request->query('designation_id'));
        }
        if (filled($request->query('branch_id'))) {
            if ($this->actingBranchId($request) === null) {
                $query->where('branch_id', (int) $request->query('branch_id'));
            }
        }
        if (filled($request->query('employment_status'))) {
            $query->where('employment_status', $request->query('employment_status'));
        }
        if (filled($request->query('employment_type'))) {
            $query->where('employment_type', $request->query('employment_type'));
        }

        $employees = $query->orderBy('employee_code')->paginate(20)->withQueryString();

        return view('hr.employees.index', [
            'institute' => $institute,
            'employees' => $employees,
            'filters' => $request->query(),
            'branches' => $this->branchOptions($institute->id),
            'departments' => HrDepartment::query()->ordered()->get(['id', 'name']),
            'designations' => HrDesignation::query()->ordered()->get(['id', 'name']),
            'statuses' => HrEmployee::EMPLOYMENT_STATUSES,
            'types' => HrEmployee::EMPLOYMENT_TYPES,
            'canCreate' => $this->can($request, ['hr.employee.create', 'hr.manage', 'hr.employee.manage']),
            'canUpdate' => $this->can($request, ['hr.employee.update', 'hr.manage', 'hr.employee.manage']),
            'canDelete' => $this->can($request, ['hr.employee.delete', 'hr.manage', 'hr.employee.manage']),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('hr.employees.form', [
            'institute' => $institute,
            'employee' => null,
            'branches' => $this->branchOptions($institute->id),
            'departments' => HrDepartment::query()->where('is_active', true)->ordered()->get(),
            'designations' => HrDesignation::query()->where('is_active', true)->ordered()->get(),
            'managers' => $this->managerOptions(),
            'statuses' => HrEmployee::EMPLOYMENT_STATUSES,
            'types' => HrEmployee::EMPLOYMENT_TYPES,
            'genders' => HrEmployee::GENDERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $this->validated($request, null);
        $data['profile_photo'] = $request->hasFile('profile_photo')
            ? $this->profileImage->processAndStore($request->file('profile_photo'), 'hr-employees')
            : null;

        $branchId = $this->resolveBranchId($request, $data['branch_id'] ?? null);

        $employee = $this->employeeService->create($data, $institute->id, $branchId, $this->actorId($request));

        return redirect()->route('hr.employees.show', $employee)->with('status', 'Employee "'.$employee->display_name.'" created ('.$employee->employee_code.').');
    }

    public function show(Request $request, HrEmployee $hrEmployee): View
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id);
        $hrEmployee->load(['branch', 'department', 'designation', 'reportingManager', 'instituteUser']);

        $histories = HrEmploymentHistory::query()
            ->where('employee_id', $hrEmployee->id)
            ->where('institute_id', $institute->id)
            ->with(['previousBranch', 'newBranch', 'previousDepartment', 'newDepartment', 'previousDesignation', 'newDesignation', 'previousManager', 'newManager', 'changedBy'])
            ->orderBy('effective_date')->orderBy('id')->get();

        $periods = HrEmploymentPeriod::query()
            ->where('employee_id', $hrEmployee->id)
            ->where('institute_id', $institute->id)
            ->orderBy('start_date')->orderBy('id')->get();

        $currentPeriod = $periods->firstWhere('status', 'active');
        $totalDays = 0;
        foreach ($periods as $p) {
            $totalDays += $p->durationInDays();
        }

        // HR-8: performance & training history for employee profile
        $performanceReviews = \App\Models\HrPerformanceReview::where('employee_id', $hrEmployee->id)->where('institute_id', $institute->id)->with(['period', 'kpis'])->orderByDesc('review_date')->limit(10)->get();
        $trainingEnrollments = \App\Models\HrTrainingEnrollment::where('employee_id', $hrEmployee->id)->where('institute_id', $institute->id)->with(['training'])->orderByDesc('created_at')->limit(10)->get();
        $skills = \App\Models\HrEmployeeSkill::where('employee_id', $hrEmployee->id)->where('institute_id', $institute->id)->orderByDesc('acquired_date')->limit(20)->get();

        return view('hr.employees.show', [
            'institute' => $institute,
            'employee' => $hrEmployee,
            'histories' => $histories,
            'periods' => $periods,
            'currentPeriod' => $currentPeriod,
            'totalServiceDays' => $totalDays,
            'performanceReviews' => $performanceReviews,
            'trainingEnrollments' => $trainingEnrollments,
            'skills' => $skills,
            'branches' => $this->branchOptions($institute->id),
            'departments' => HrDepartment::query()->where('is_active', true)->ordered()->get(),
            'designations' => HrDesignation::query()->where('is_active', true)->ordered()->get(),
            'managers' => $this->managerOptions($hrEmployee->id),
            'canUpdate' => $this->can($request, ['hr.employee.update', 'hr.manage']),
            'canDelete' => $this->can($request, ['hr.employee.delete', 'hr.manage']),
            'canTransfer' => $this->can($request, ['hr.transfer', 'hr.employee.update', 'hr.manage', 'hr.employee.manage']),
            'canPromote' => $this->can($request, ['hr.promotion', 'hr.employee.update', 'hr.manage', 'hr.employee.manage']),
            'canResign' => $this->can($request, ['hr.resignation', 'hr.employee.manage', 'hr.manage']),
            'canTerminate' => $this->can($request, ['hr.termination', 'hr.employee.manage', 'hr.manage']),
            'canReactivate' => $this->can($request, ['hr.reactivation', 'hr.employee.manage', 'hr.manage']),
            'canHistory' => $this->can($request, ['hr.history.view', 'hr.employee.view', 'hr.manage']),
            'canDocView' => $this->can($request, ['hr.document.view', 'hr.document.manage', 'hr.manage']),
            'canDocManage' => $this->can($request, ['hr.document.manage', 'hr.manage']),
            'canDocVerify' => $this->can($request, ['hr.document.verify', 'hr.document.manage', 'hr.manage']),
        ]);
    }

    public function edit(Request $request, HrEmployee $hrEmployee): View
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id);

        return view('hr.employees.form', [
            'institute' => $institute,
            'employee' => $hrEmployee,
            'branches' => $this->branchOptions($institute->id),
            'departments' => HrDepartment::query()->where('is_active', true)->ordered()->get(),
            'designations' => HrDesignation::query()->where('is_active', true)->ordered()->get(),
            'managers' => $this->managerOptions($hrEmployee->id),
            'statuses' => HrEmployee::EMPLOYMENT_STATUSES,
            'types' => HrEmployee::EMPLOYMENT_TYPES,
            'genders' => HrEmployee::GENDERS,
        ]);
    }

    public function update(Request $request, HrEmployee $hrEmployee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id);
        $data = $this->validated($request, $hrEmployee->id);

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $this->profileImage->processAndStore($request->file('profile_photo'), 'hr-employees');
        } elseif ($request->boolean('remove_photo')) {
            $data['profile_photo'] = null;
        }

        $branchId = $this->resolveBranchId($request, $data['branch_id'] ?? null);

        $this->employeeService->update($hrEmployee, $data, $institute->id, $branchId, $this->actorId($request));

        return redirect()->route('hr.employees.show', $hrEmployee)->with('status', 'Employee updated.');
    }

    public function destroy(Request $request, HrEmployee $hrEmployee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id);
        $this->employeeService->delete($hrEmployee, $institute->id, $this->actorId($request), $this->actingBranchId($request));

        return redirect()->route('hr.employees.index')->with('status', 'Employee deleted.');
    }

    // ---------------- HR-2 Lifecycle

    public function transfer(Request $request, HrEmployee $hrEmployee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id);

        $data = $request->validate([
            'effective_date' => ['required', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:hr_designations,id'],
            'reporting_manager_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'employment_type' => ['nullable', Rule::in(HrEmployee::EMPLOYMENT_TYPES)],
            'employment_status' => ['nullable', Rule::in(HrEmployee::EMPLOYMENT_STATUSES)],
            'salary_reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->lifecycle->transfer($hrEmployee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', 'Employment transfer recorded.');
    }

    public function promote(Request $request, HrEmployee $hrEmployee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id);

        $data = $request->validate([
            'effective_date' => ['required', 'date'],
            'designation_id' => ['nullable', 'integer', 'exists:hr_designations,id'],
            'department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'title' => ['nullable', 'string', 'max:150'],
            'salary_reference' => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', Rule::in(['promotion', 'demotion'])],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->lifecycle->promote($hrEmployee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', ucfirst($data['event_type'] ?? 'promotion').' recorded.');
    }

    public function resign(Request $request, HrEmployee $hrEmployee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id);

        $data = $request->validate([
            'resignation_date' => ['required', 'date'],
            'last_working_date' => ['required', 'date', 'after_or_equal:resignation_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->lifecycle->resign($hrEmployee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', 'Resignation recorded (pending approval).');
    }

    public function resignDecision(Request $request, HrEmploymentHistory $history): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $this->lifecycle->approveResignation($history, $institute->id, $this->actorId($request), $data['decision']);

        return back()->with('status', 'Resignation '.($data['decision'] === 'approved' ? 'approved' : 'rejected').'.');
    }

    public function terminate(Request $request, HrEmployee $hrEmployee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id);

        $data = $request->validate([
            'termination_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->lifecycle->terminate($hrEmployee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', 'Employee terminated.');
    }

    public function reactivate(Request $request, HrEmployee $hrEmployee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id);

        $data = $request->validate([
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->lifecycle->reactivate($hrEmployee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', 'Employee reactivated.');
    }

    private function can(Request $request, array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if ($request->user()->hasPermission($perm)) {
                return true;
            }
        }

        return false;
    }

    private function ensureSameInstitute(HrEmployee $employee, int $instituteId): void
    {
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);
        $acting = $this->actingBranchId(request());
        if ($acting !== null && $employee->branch_id !== null && (int) $employee->branch_id !== (int) $acting) {
            abort(404);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['nullable', Rule::in(HrEmployee::GENDERS)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'national_id' => ['nullable', 'string', 'max:60'],
            'passport_no' => ['nullable', 'string', 'max:60'],
            'joining_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', Rule::in(HrEmployee::EMPLOYMENT_STATUSES)],
            'employment_type' => ['nullable', Rule::in(HrEmployee::EMPLOYMENT_TYPES)],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:hr_designations,id'],
            'reporting_manager_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:100'],
        ]);
    }

    private function resolveBranchId(Request $request, ?int $validatedBranchId): ?int
    {
        $acting = $this->actingBranchId($request);

        return $acting ?? $validatedBranchId;
    }

    private function branchOptions(int $instituteId)
    {
        $acting = $this->actingBranchId(request());

        return Branch::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->when($acting !== null, fn ($q) => $q->whereKey($acting))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function managerOptions(?int $excludeId = null)
    {
        return HrEmployee::query()
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('employment_status', 'active')
            ->orderBy('display_name')
            ->limit(200)
            ->get(['id', 'display_name', 'employee_code']);
    }
}
