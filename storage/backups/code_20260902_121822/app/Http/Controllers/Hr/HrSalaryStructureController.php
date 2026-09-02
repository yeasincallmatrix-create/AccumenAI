<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\HrSalaryStructure;
use App\Services\HrSalaryStructureService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrSalaryStructureController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrSalaryStructureService $service) {}

    private function can(Request $request, array $perms): bool
    {
        foreach ($perms as $p) if ($request->user()->hasPermission($p)) return true;
        return false;
    }

    public function index(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $structures = HrSalaryStructure::where('institute_id', $institute->id)
            ->with(['branch','department','currency'])
            ->orderBy('name')->paginate(20);
        return view('hr.salary-structures.index', [
            'institute' => $institute,
            'structures' => $structures,
            'canManage' => $this->can($request, ['hr.salary.manage','hr.manage']),
        ]);
    }

    public function create(Request $request)
    {
        $institute = $this->requireInstitute($request);
        return view('hr.salary-structures.form', [
            'institute' => $institute,
            'structure' => null,
            'currencies' => Currency::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $data = $this->validated($request);
        $branchId = $this->actingBranchId($request) ?? ($data['branch_id'] ?? null);
        $structure = $this->service->create($data, $institute->id, $branchId, $this->actorId($request));
        return redirect()->route('hr.salary-structures.index')->with('status', 'Salary structure "'.$structure->name.'" created.');
    }

    public function edit(Request $request, HrSalaryStructure $hrSalaryStructure)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $hrSalaryStructure->institute_id !== (int) $institute->id, 404);
        return view('hr.salary-structures.form', [
            'institute' => $institute,
            'structure' => $hrSalaryStructure->load('components'),
            'currencies' => Currency::orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, HrSalaryStructure $hrSalaryStructure)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $hrSalaryStructure->institute_id !== (int) $institute->id, 404);
        $data = $this->validated($request, $hrSalaryStructure->id);
        $this->service->update($hrSalaryStructure, $data, $this->actorId($request));
        return redirect()->route('hr.salary-structures.index')->with('status', 'Salary structure updated.');
    }

    public function toggle(Request $request, HrSalaryStructure $hrSalaryStructure)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $hrSalaryStructure->institute_id !== (int) $institute->id, 404);
        $this->service->toggle($hrSalaryStructure, $this->actorId($request));
        return back()->with('status', 'Status toggled.');
    }

    public function destroy(Request $request, HrSalaryStructure $hrSalaryStructure)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $hrSalaryStructure->institute_id !== (int) $institute->id, 404);
        $this->service->delete($hrSalaryStructure, $this->actorId($request));
        return redirect()->route('hr.salary-structures.index')->with('status', 'Deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required','string','max:120'],
            'code' => ['required','string','max:40'],
            'branch_id' => ['nullable','integer','exists:branches,id'],
            'department_id' => ['nullable','integer','exists:hr_departments,id'],
            'currency_id' => ['nullable','integer','exists:currencies,id'],
            'pay_frequency' => ['nullable', Rule::in(['monthly','weekly','biweekly','fortnightly'])],
            'effective_from' => ['required','date'],
            'effective_to' => ['nullable','date','after:effective_from'],
            'basic_salary' => ['required','numeric','min:0'],
            'housing_allowance' => ['nullable','numeric','min:0'],
            'medical_allowance' => ['nullable','numeric','min:0'],
            'transport_allowance' => ['nullable','numeric','min:0'],
            'other_allowance' => ['nullable','numeric','min:0'],
            'overtime_rate' => ['nullable','numeric','min:0'],
            'bonus_amount' => ['nullable','numeric','min:0'],
            'commission_amount' => ['nullable','numeric','min:0'],
            'deduction_amount' => ['nullable','numeric','min:0'],
            'tax_deduction' => ['nullable','numeric','min:0'],
            'is_active' => ['nullable','boolean'],
            'notes' => ['nullable','string','max:2000'],
        ]);
    }
}
