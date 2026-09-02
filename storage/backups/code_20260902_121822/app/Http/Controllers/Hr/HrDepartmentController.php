<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\HrDepartment;
use App\Services\HrDepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Department management — HR-1.
 *
 * Security: institute_id / branch_id never from request input; resolved from auth. Branch managers pinned via actingBranchId().
 */
class HrDepartmentController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrDepartmentService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = HrDepartment::query()->with(['branch', 'parent'])->orderBy('display_order')->orderBy('name');

        if (filled($q = trim((string) $request->query('q')))) {
            $query->where(function ($b) use ($q) {
                $b->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%");
            });
        }
        if (filled($request->query('is_active'))) {
            $query->where('is_active', $request->query('is_active') === '1');
        }

        $departments = $query->paginate(20)->withQueryString();

        return view('hr.departments.index', [
            'institute' => $institute,
            'departments' => $departments,
            'branches' => $this->branchOptions($institute->id),
            'parents' => $this->parentOptions($institute->id),
            'filters' => $request->query(),
            'canCreate' => $request->user()->hasPermission('hr.department.create') || $request->user()->hasPermission('hr.manage'),
            'canUpdate' => $request->user()->hasPermission('hr.department.update') || $request->user()->hasPermission('hr.manage'),
            'canDelete' => $request->user()->hasPermission('hr.department.delete') || $request->user()->hasPermission('hr.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $this->validated($request);

        $this->service->create($data, $institute->id, $this->actorId($request), $this->actingBranchId($request));

        return back()->with('status', 'Department created.');
    }

    public function update(Request $request, HrDepartment $hrDepartment): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $this->validated($request);

        $this->service->update($hrDepartment, $data, $institute->id, $this->actorId($request), $this->actingBranchId($request));

        return back()->with('status', 'Department updated.');
    }

    public function destroy(Request $request, HrDepartment $hrDepartment): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->service->delete($hrDepartment, $institute->id, $this->actorId($request), $this->actingBranchId($request));

        return back()->with('status', 'Department deleted.');
    }

    public function toggle(Request $request, HrDepartment $hrDepartment): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->service->toggleActive($hrDepartment, $institute->id, $this->actorId($request), $this->actingBranchId($request));

        return back()->with('status', 'Department status toggled.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'parent_department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
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

    private function parentOptions(int $instituteId)
    {
        return HrDepartment::query()->orderBy('name')->get(['id', 'name', 'branch_id']);
    }
}
