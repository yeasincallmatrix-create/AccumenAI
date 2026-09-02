<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Services\HrDesignationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Designation management — HR-1.
 */
class HrDesignationController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrDesignationService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = HrDesignation::query()->with('department')->orderBy('display_order')->orderBy('name');

        if (filled($q = trim((string) $request->query('q')))) {
            $query->where(function ($b) use ($q) {
                $b->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%");
            });
        }
        if (filled($request->query('is_active'))) {
            $query->where('is_active', $request->query('is_active') === '1');
        }

        $designations = $query->paginate(20)->withQueryString();

        $departments = HrDepartment::query()->orderBy('name')->get(['id', 'name']);

        return view('hr.designations.index', [
            'institute' => $institute,
            'designations' => $designations,
            'departments' => $departments,
            'filters' => $request->query(),
            'canCreate' => $request->user()->hasPermission('hr.designation.create') || $request->user()->hasPermission('hr.manage'),
            'canUpdate' => $request->user()->hasPermission('hr.designation.update') || $request->user()->hasPermission('hr.manage'),
            'canDelete' => $request->user()->hasPermission('hr.designation.delete') || $request->user()->hasPermission('hr.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $this->validated($request);

        $this->service->create($data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Designation created.');
    }

    public function update(Request $request, HrDesignation $hrDesignation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $this->validated($request);

        $this->service->update($hrDesignation, $data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Designation updated.');
    }

    public function destroy(Request $request, HrDesignation $hrDesignation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->service->delete($hrDesignation, $institute->id, $this->actorId($request));

        return back()->with('status', 'Designation deleted.');
    }

    public function toggle(Request $request, HrDesignation $hrDesignation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->service->toggleActive($hrDesignation, $institute->id, $this->actorId($request));

        return back()->with('status', 'Designation status toggled.');
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
            'department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
