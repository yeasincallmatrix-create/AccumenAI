<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\AdministrativeUnit;
use App\Models\Branch;
use App\Models\Country;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\HrEmployeeSkill;
use App\Models\HrEmploymentHistory;
use App\Models\HrEmploymentPeriod;
use App\Models\HrPerformanceReview;
use App\Models\HrTrainingEnrollment;
use App\Models\Institute;
use App\Services\HrEmployeeService;
use App\Services\HrEmploymentLifecycleService;
use App\Services\ProfileImageService;
use App\Support\GeoHierarchy;
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

        $employee = new HrEmployee(['institute_id' => $institute->id]);
        $countryId = $this->instituteCountryId($institute);

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
            'presentAddress' => $this->addressData($employee, 'present_', $countryId),
            'permanentAddress' => $this->addressData($employee, 'permanent_', $countryId),
            'defaultCountryId' => $countryId,
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

    public function show(Request $request, HrEmployee $employee): View
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($employee, $institute->id);
        $employee->load(['branch', 'department', 'designation', 'reportingManager', 'instituteUser']);

        $histories = HrEmploymentHistory::query()
            ->where('employee_id', $employee->id)
            ->where('institute_id', $institute->id)
            ->with(['previousBranch', 'newBranch', 'previousDepartment', 'newDepartment', 'previousDesignation', 'newDesignation', 'previousManager', 'newManager', 'changedBy'])
            ->orderBy('effective_date')->orderBy('id')->get();

        $periods = HrEmploymentPeriod::query()
            ->where('employee_id', $employee->id)
            ->where('institute_id', $institute->id)
            ->orderBy('start_date')->orderBy('id')->get();

        $currentPeriod = $periods->firstWhere('status', 'active');
        $totalDays = 0;
        foreach ($periods as $p) {
            $totalDays += $p->durationInDays();
        }

        // HR-8: performance & training history for employee profile
        $performanceReviews = HrPerformanceReview::where('employee_id', $employee->id)->where('institute_id', $institute->id)->with(['period', 'kpis'])->orderByDesc('review_date')->limit(10)->get();
        $trainingEnrollments = HrTrainingEnrollment::where('employee_id', $employee->id)->where('institute_id', $institute->id)->with(['training'])->orderByDesc('created_at')->limit(10)->get();
        $skills = HrEmployeeSkill::where('employee_id', $employee->id)->where('institute_id', $institute->id)->orderByDesc('acquired_date')->limit(20)->get();

        return view('hr.employees.show', [
            'institute' => $institute,
            'employee' => $employee,
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
            'managers' => $this->managerOptions($employee->id),
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

    public function edit(Request $request, HrEmployee $employee): View
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($employee, $institute->id);

        return view('hr.employees.form', [
            'institute' => $institute,
            'employee' => $employee,
            'branches' => $this->branchOptions($institute->id),
            'departments' => HrDepartment::query()->where('is_active', true)->ordered()->get(),
            'designations' => HrDesignation::query()->where('is_active', true)->ordered()->get(),
            'managers' => $this->managerOptions($employee->id),
            'statuses' => HrEmployee::EMPLOYMENT_STATUSES,
            'types' => HrEmployee::EMPLOYMENT_TYPES,
            'genders' => HrEmployee::GENDERS,
            'presentAddress' => $this->addressData($employee, 'present_'),
            'permanentAddress' => $this->addressData($employee, 'permanent_'),
            'defaultCountryId' => $employee->present_country_id ?: $employee->permanent_country_id ?: $this->instituteCountryId($institute),
        ]);
    }

    public function update(Request $request, HrEmployee $employee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($employee, $institute->id);
        $data = $this->validated($request, $employee->id);

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $this->profileImage->processAndStore($request->file('profile_photo'), 'hr-employees');
        } elseif ($request->boolean('remove_photo')) {
            $data['profile_photo'] = null;
        }

        $branchId = $this->resolveBranchId($request, $data['branch_id'] ?? null);

        $this->employeeService->update($employee, $data, $institute->id, $branchId, $this->actorId($request));

        return redirect()->route('hr.employees.show', $employee)->with('status', 'Employee updated.');
    }

    public function destroy(Request $request, HrEmployee $employee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($employee, $institute->id);
        $this->employeeService->delete($employee, $institute->id, $this->actorId($request), $this->actingBranchId($request));

        return redirect()->route('hr.employees.index')->with('status', 'Employee deleted.');
    }

    // ---------------- HR-2 Lifecycle

    public function transfer(Request $request, HrEmployee $employee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($employee, $institute->id);

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

        $this->lifecycle->transfer($employee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', 'Employment transfer recorded.');
    }

    public function promote(Request $request, HrEmployee $employee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($employee, $institute->id);

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

        $this->lifecycle->promote($employee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', ucfirst($data['event_type'] ?? 'promotion').' recorded.');
    }

    public function resign(Request $request, HrEmployee $employee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($employee, $institute->id);

        $data = $request->validate([
            'resignation_date' => ['required', 'date'],
            'last_working_date' => ['required', 'date', 'after_or_equal:resignation_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->lifecycle->resign($employee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

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

    public function terminate(Request $request, HrEmployee $employee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($employee, $institute->id);

        $data = $request->validate([
            'termination_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->lifecycle->terminate($employee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', 'Employee terminated.');
    }

    public function reactivate(Request $request, HrEmployee $employee): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($employee, $institute->id);

        $data = $request->validate([
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->lifecycle->reactivate($employee, $data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

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
        // The expertise chip input posts a JSON string in a hidden field.
        $request->merge(['expertise' => $this->expertiseList($request->input('expertise'))]);

        return $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['nullable', Rule::in(HrEmployee::GENDERS)],
            'blood_group' => ['nullable', Rule::in(HrEmployee::BLOOD_GROUPS)],
            'marital_status' => ['nullable', Rule::in(HrEmployee::MARITAL_STATUSES)],
            'education_qualification' => ['nullable', 'string', 'max:500'],
            'expertise' => ['nullable', 'array'],
            'expertise.*' => ['string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'present_address' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'present_country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'present_admin_1_id' => ['nullable', 'integer', Rule::exists('administrative_units', 'id')],
            'present_admin_2_id' => ['nullable', 'integer', Rule::exists('administrative_units', 'id')],
            'present_admin_3_id' => ['nullable', 'integer', Rule::exists('administrative_units', 'id')],
            'present_zip_code' => ['nullable', 'string', 'max:10'],
            'permanent_country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'permanent_admin_1_id' => ['nullable', 'integer', Rule::exists('administrative_units', 'id')],
            'permanent_admin_2_id' => ['nullable', 'integer', Rule::exists('administrative_units', 'id')],
            'permanent_admin_3_id' => ['nullable', 'integer', Rule::exists('administrative_units', 'id')],
            'permanent_zip_code' => ['nullable', 'string', 'max:10'],
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

    /**
     * Normalise the expertise hidden field (JSON string) into a trimmed,
     * de-duplicated list of tags.
     *
     * @return array<int,string>
     */
    private function expertiseList(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
            $item = trim((string) $item);
            if ($item === '' || in_array($item, $items, true)) {
                continue;
            }
            $items[] = mb_substr($item, 0, 100);
        }

        return $items;
    }

    /**
     * Data for the country-neutral <x-address> selector: the selected country
     * plus the per-level labels and unit options for the cascade.
     *
     * @return array{country: ?Country, level_labels: array<int, string>, level_options: array<int, array<int, string>>}
     */
    private function addressData(HrEmployee $employee, string $prefix, ?int $fallbackCountryId = null): array
    {
        $countryId = (int) ($employee->getAttribute($prefix.'country_id') ?? $fallbackCountryId) ?: 0;
        $country = $countryId ? Country::find($countryId) : null;

        $levelOptions = [1 => [], 2 => [], 3 => []];

        if ($country) {
            $levels = $country->selectableLevels()->orderBy('level_number')->get();
            foreach ($levels as $level) {
                $query = AdministrativeUnit::query()
                    ->where('country_id', $country->id)
                    ->where('administrative_level_id', $level->id)
                    ->where('status', true);

                if ($level->level_number > 1) {
                    $parentAttr = $prefix.'admin_'.($level->level_number - 1).'_id';
                    $query->where('parent_id', (int) ($employee->getAttribute($parentAttr) ?? 0));
                } else {
                    $query->whereNull('parent_id');
                }

                $levelOptions[$level->level_number] = $query
                    ->orderBy('name')
                    ->get()
                    ->pluck('name', 'id')
                    ->all();
            }
        }

        return [
            'country' => $country,
            'level_labels' => $country ? GeoHierarchy::levelLabels($country) : [],
            'level_options' => $levelOptions,
        ];
    }

    /**
     * Country of the institute, used so the address cascades render with a
     * pre-selected country on a brand-new employee form.
     */
    private function instituteCountryId(Institute $institute): ?int
    {
        $id = Country::query()->where('name', $institute->country)->value('id');

        return $id !== null ? (int) $id : null;
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
