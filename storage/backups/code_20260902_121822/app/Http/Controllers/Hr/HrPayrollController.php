<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\HrEmployee;
use App\Models\HrPayroll;
use App\Models\HrPayrollPeriod;
use App\Services\HrPayrollService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrPayrollController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrPayrollService $payrolls) {}

    private function can(Request $request, array $perms): bool
    {
        foreach ($perms as $p) {
            if ($request->user()->hasPermission($p)) {
                return true;
            }
        }

        return false;
    }

    private function ensureSameInstitute($model, int $instituteId, ?int $branchId): void
    {
        abort_if((int) $model->institute_id !== (int) $instituteId, 404);
        if ($branchId !== null && $model->branch_id !== null && (int) $model->branch_id !== (int) $branchId) {
            abort(404);
        }
    }

    // ---------------- Salary Assignment

    public function assignSalary(Request $request, HrEmployee $hrEmployee)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id, $this->actingBranchId($request));
        $data = $request->validate([
            'salary_structure_id' => ['nullable', 'integer', 'exists:hr_salary_structures,id'],
            'effective_date' => ['required', 'date'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'pay_frequency' => ['nullable', Rule::in(['monthly', 'weekly', 'biweekly', 'fortnightly'])],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0'],
            'bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_deduction' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['employee_id'] = $hrEmployee->id;
        $assignment = $this->payrolls->assignSalary($data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Salary assigned effective '.$assignment->effective_date->format('Y-m-d'));
    }

    // ---------------- Periods

    public function periodsIndex(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $periods = HrPayrollPeriod::where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('start_date')->paginate(20);

        return view('hr.payroll.periods', [
            'institute' => $institute,
            'periods' => $periods,
            'canManage' => $this->can($request, ['hr.payroll.manage', 'hr.manage']),
            'currencies' => Currency::orderBy('code')->get(),
        ]);
    }

    public function createPeriod(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'pay_frequency' => ['nullable', Rule::in(['monthly', 'weekly', 'biweekly', 'fortnightly'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
        ]);
        $branchId = $this->actingBranchId($request);
        $period = $this->payrolls->createPeriod($data, $institute->id, $branchId, $this->actorId($request));

        return redirect()->route('hr.payroll.periods.show', $period)->with('status', 'Payroll period created.');
    }

    public function showPeriod(Request $request, HrPayrollPeriod $hrPayrollPeriod)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrPayrollPeriod, $institute->id, $this->actingBranchId($request));
        $hrPayrollPeriod->load(['currency']);
        $payrolls = HrPayroll::where('payroll_period_id', $hrPayrollPeriod->id)
            ->with(['employee', 'currency'])
            ->orderBy('payslip_no')->paginate(20);

        return view('hr.payroll.show', [
            'institute' => $institute,
            'period' => $hrPayrollPeriod,
            'payrolls' => $payrolls,
            'canApprove' => $this->can($request, ['hr.payroll.approve', 'hr.payroll.manage', 'hr.manage']),
            'canPay' => $this->can($request, ['hr.payroll.pay', 'hr.payroll.manage', 'hr.manage']),
        ]);
    }

    public function generate(Request $request, HrPayrollPeriod $hrPayrollPeriod)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrPayrollPeriod, $institute->id, $this->actingBranchId($request));
        $this->payrolls->generate($hrPayrollPeriod, $this->actorId($request));

        return back()->with('status', 'Payroll generated.');
    }

    public function preview(Request $request, HrEmployee $hrEmployee, HrPayrollPeriod $hrPayrollPeriod)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, $institute->id, $this->actingBranchId($request));
        $this->ensureSameInstitute($hrPayrollPeriod, $institute->id, $this->actingBranchId($request));
        $calc = $this->payrolls->preview($hrEmployee->id, $hrPayrollPeriod->id, $institute->id);

        return response()->json(['success' => true, 'data' => $calc]);
    }

    public function approve(Request $request, HrPayrollPeriod $hrPayrollPeriod)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrPayrollPeriod, $institute->id, $this->actingBranchId($request));
        if (! $this->can($request, ['journals.post', 'hr.payroll.approve', 'hr.payroll.manage', 'hr.manage'])) {
            abort(403, 'Finance posting permission required.');
        }
        $this->payrolls->approve($hrPayrollPeriod, $this->actorId($request));

        return back()->with('status', 'Payroll approved and journals posted.');
    }

    public function pay(Request $request, HrPayrollPeriod $hrPayrollPeriod)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrPayrollPeriod, $institute->id, $this->actingBranchId($request));
        if (! $this->can($request, ['journals.post', 'hr.payroll.pay', 'hr.payroll.manage', 'hr.manage'])) {
            abort(403, 'Finance payment permission required.');
        }
        $data = $request->validate(['payment_method' => ['nullable', 'string', 'max:50']]);
        $this->payrolls->markPaid($hrPayrollPeriod, $this->actorId($request), $data['payment_method'] ?? null);

        return back()->with('status', 'Payroll marked paid and payment journals posted.');
    }

    public function cancel(Request $request, HrPayrollPeriod $hrPayrollPeriod)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrPayrollPeriod, $institute->id, $this->actingBranchId($request));
        if (! $this->can($request, ['journals.reverse', 'hr.payroll.manage', 'hr.manage'])) {
            abort(403, 'Finance reversal permission required.');
        }
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->payrolls->cancelPeriod($hrPayrollPeriod, $this->actorId($request), $data['reason'] ?? null);

        return back()->with('status', 'Payroll cancelled (reversal journals posted, historical preserved).');
    }

    // ---------------- Payslip

    public function payslip(Request $request, HrPayroll $hrPayroll)
    {
        $institute = $this->requireInstitute($request);
        // Check payslip ownership: hr.payroll.view OR hr.payslip.own via linked institute_user
        $canViewAll = $this->can($request, ['hr.payroll.view', 'hr.payroll.manage', 'hr.manage']);
        $canOwn = $this->can($request, ['hr.payslip.own']);
        if (! $canViewAll && ! $canOwn) {
            abort(403);
        }

        $this->ensureSameInstitute($hrPayroll, $institute->id, $this->actingBranchId($request));

        if (! $canViewAll && $canOwn) {
            $actor = $request->user();
            // If InstituteUser linked to employee via institute_user_id, allow own
            $linked = $hrPayroll->employee->institute_user_id !== null && (int) $hrPayroll->employee->institute_user_id === (int) $actor->id;
            if (! $linked) {
                abort(403);
            }
        }

        $hrPayroll->load(['employee', 'period', 'items', 'currency', 'journal', 'paymentJournal']);

        return view('hr.payroll.payslip', [
            'institute' => $institute,
            'payroll' => $hrPayroll,
            'employee' => $hrPayroll->employee,
            'period' => $hrPayroll->period,
        ]);
    }

    // ---------------- Adjustments

    public function addAdjustment(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'payroll_id' => ['nullable', 'integer', 'exists:hr_payrolls,id'],
            'payroll_period_id' => ['nullable', 'integer', 'exists:hr_payroll_periods,id'],
            'adjustment_type' => ['required', Rule::in(['bonus', 'deduction', 'allowance', 'correction', 'overtime', 'commission', 'tax'])],
            'amount' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $adj = $this->payrolls->addAdjustment($data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Adjustment added.');
    }

    // ---------------- Reports

    public function reports(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $stats = $this->payrolls->reports($institute->id, $branchId);
        $periods = HrPayrollPeriod::where('institute_id', $institute->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('start_date')->limit(10)->get();
        $recent = HrPayroll::where('institute_id', $institute->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['employee', 'period'])->latest('id')->limit(10)->get();
        $byDept = $stats['by_department'];

        return view('hr.payroll.reports', compact('institute', 'stats', 'periods', 'recent', 'byDept'));
    }

    public function register(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $payrolls = HrPayroll::where('institute_id', $institute->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($request->filled('period_id'), fn ($q) => $q->where('payroll_period_id', $request->integer('period_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->with(['employee', 'period'])->orderByDesc('id')->paginate(20)->withQueryString();
        $periods = HrPayrollPeriod::where('institute_id', $institute->id)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->orderByDesc('start_date')->get();

        return view('hr.payroll.register', compact('institute', 'payrolls', 'periods'));
    }

    public function reconciliation(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request) ?? ($request->filled('branch_id') ? $request->integer('branch_id') : null);
        $periodId = $request->filled('period_id') ? $request->integer('period_id') : null;
        if ($periodId) {
            $period = HrPayrollPeriod::where('institute_id', $institute->id)->where('id', $periodId)->firstOrFail();
            $this->ensureSameInstitute($period, $institute->id, $this->actingBranchId($request));
        }
        $data = $this->payrolls->reconciliation($institute->id, $branchId, $periodId);
        $periods = HrPayrollPeriod::where('institute_id', $institute->id)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->orderByDesc('start_date')->get();
        $branches = Branch::where('institute_id', $institute->id)->orderBy('name')->get(['id', 'name']);

        return view('hr.payroll.reconciliation', compact('institute', 'data', 'periods', 'branches'));
    }
}
