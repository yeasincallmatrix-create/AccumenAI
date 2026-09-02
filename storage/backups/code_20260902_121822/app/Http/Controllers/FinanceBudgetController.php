<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Budget;
use App\Models\FiscalYear;
use App\Services\Accounting\BudgetApprovalService;
use App\Services\Accounting\BudgetCalculationService;
use App\Services\Accounting\BudgetForecastService;
use App\Services\Accounting\BudgetService;
use App\Support\CsvStream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceBudgetController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly BudgetService $budgets,
        private readonly BudgetCalculationService $calc,
        private readonly BudgetForecastService $forecast,
        private readonly BudgetApprovalService $approval,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        return view('institute.finance.budgets.index', [
            'budgets' => $this->budgets->list($institute->id, $branchId, [
                'status' => $request->query('status'),
                'type' => $request->query('type'),
                'fiscal_year_id' => $request->query('fiscal_year_id'),
            ]),
            'fiscalYears' => FiscalYear::where('institute_id', $institute->id)->orderByDesc('start_date')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.budgets.form', [
            'budget' => null,
            'accounts' => $this->budgets->accounts($institute->id, $this->actingBranchId($request)),
            'fiscalYears' => FiscalYear::where('institute_id', $institute->id)->where('status', 'open')->orderByDesc('start_date')->get(),
            'currency' => $this->budgets->defaultCurrency($institute->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:revenue,expense,cost,asset',
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'currency_id' => 'required|exists:currencies,id',
            'notes' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.coa_id' => 'required_with:lines|exists:chart_of_accounts,id',
            'lines.*.month' => 'nullable|integer|min:0|max:12',
            'lines.*.amount' => 'required_with:lines|numeric|min:0',
            'lines.*.notes' => 'nullable|string',
        ]);

        $budget = $this->budgets->create(
            $institute->id,
            $this->actingBranchId($request),
            $data,
            $this->actorId($request)
        );

        return redirect()->route('finance.budgets.show', $budget->id)
            ->with('status', 'Budget created successfully.');
    }

    public function show(Request $request, Budget $budget): View
    {
        $institute = $this->requireInstitute($request);
        $budget = $this->budgets->getBudget($institute->id, $budget->id);

        return view('institute.finance.budgets.show', [
            'budget' => $budget,
            'comparison' => $this->calc->budgetVsActualForBudget(
                $institute->id,
                $this->actingBranchId($request),
                $budget->id
            ),
            'alerts' => $this->approval->checkAlerts(
                $institute->id,
                $this->actingBranchId($request),
                $budget->fiscal_year_id
            ),
        ]);
    }

    public function edit(Request $request, Budget $budget): View
    {
        $institute = $this->requireInstitute($request);

        if (!$budget->isEditable()) {
            abort(403, 'This budget cannot be edited in its current status.');
        }

        $budget->load('versions.lines');

        return view('institute.finance.budgets.form', [
            'budget' => $budget,
            'accounts' => $this->budgets->accounts($institute->id, $this->actingBranchId($request)),
            'fiscalYears' => FiscalYear::where('institute_id', $institute->id)->orderByDesc('start_date')->get(),
            'currency' => $budget->currency,
        ]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'notes' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.coa_id' => 'required_with:lines|exists:chart_of_accounts,id',
            'lines.*.month' => 'nullable|integer|min:0|max:12',
            'lines.*.amount' => 'required_with:lines|numeric|min:0',
            'lines.*.notes' => 'nullable|string',
        ]);

        $this->budgets->update($budget, $data, $this->actorId($request));

        return redirect()->route('finance.budgets.show', $budget->id)
            ->with('status', 'Budget updated successfully.');
    }

    public function submit(Request $request, Budget $budget): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->budgets->submit($budget, $this->actorId($request));

        return redirect()->route('finance.budgets.show', $budget->id)
            ->with('status', 'Budget submitted for approval.');
    }

    public function approve(Request $request, Budget $budget): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->budgets->approve($budget, $this->actorId($request));

        return redirect()->route('finance.budgets.show', $budget->id)
            ->with('status', 'Budget approved.');
    }

    public function reject(Request $request, Budget $budget): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $this->budgets->reject($budget, $data['reason'], $this->actorId($request));

        return redirect()->route('finance.budgets.show', $budget->id)
            ->with('status', 'Budget rejected.');
    }

    public function lock(Request $request, Budget $budget): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->budgets->lock($budget, $this->actorId($request));

        return redirect()->route('finance.budgets.show', $budget->id)
            ->with('status', 'Budget locked.');
    }

    public function revise(Request $request, Budget $budget): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.coa_id' => 'required_with:lines|exists:chart_of_accounts,id',
            'lines.*.month' => 'nullable|integer|min:0|max:12',
            'lines.*.amount' => 'required_with:lines|numeric|min:0',
            'lines.*.notes' => 'nullable|string',
        ]);

        $this->budgets->revise($budget, $data, $this->actorId($request));

        return redirect()->route('finance.budgets.show', $budget->id)
            ->with('status', 'Budget revised. New draft version created.');
    }

    public function dashboard(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $fy = FiscalYear::where('institute_id', $institute->id)
            ->where('status', 'open')
            ->where('is_current', true)
            ->first();

        $comparison = $fy
            ? $this->calc->budgetVsActual($institute->id, $branchId, $fy->id)
            : null;

        $forecast = $fy
            ? $this->forecast->forecast($institute->id, $branchId, $fy->id)
            : null;

        $alerts = $fy
            ? $this->approval->checkAlerts($institute->id, $branchId, $fy->id)
            : [];

        $variance = $fy
            ? $this->calc->varianceAnalysis($institute->id, $branchId, $fy->id)
            : null;

        return view('institute.finance.budgets.dashboard', [
            'budgets' => $fy ? \App\Models\Budget::where('institute_id', $institute->id)
                ->where('fiscal_year_id', $fy->id)
                ->with(['fiscalYear', 'currency'])
                ->get() : collect(),
            'comparison' => $comparison,
            'forecast' => $forecast,
            'alerts' => $alerts,
            'variance' => $variance,
            'fiscalYear' => $fy,
        ]);
    }

    public function reports(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $fy = FiscalYear::where('institute_id', $institute->id)
            ->where('status', 'open')
            ->where('is_current', true)
            ->first();

        $comparison = $fy
            ? $this->calc->budgetVsActual($institute->id, $branchId, $fy->id)
            : null;

        $monthly = $fy
            ? $this->calc->monthlyActuals($institute->id, $branchId, $fy->id)
            : collect();

        return view('institute.finance.budgets.reports', [
            'comparison' => $comparison,
            'monthly' => $monthly,
            'fiscalYear' => $fy,
            'fiscalYears' => FiscalYear::where('institute_id', $institute->id)->orderByDesc('start_date')->get(),
        ]);
    }

    public function forecast(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $fy = FiscalYear::where('institute_id', $institute->id)
            ->where('status', 'open')
            ->where('is_current', true)
            ->first();

        $forecastData = $fy
            ? $this->forecast->forecast($institute->id, $branchId, $fy->id)
            : null;

        $cashFlow = $fy
            ? $this->forecast->cashFlowPlan($institute->id, $branchId, $fy->id)
            : null;

        return view('institute.finance.budgets.forecast', [
            'forecast' => $forecastData,
            'cashFlow' => $cashFlow,
            'fiscalYear' => $fy,
        ]);
    }

    public function exportComparison(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $fy = FiscalYear::where('institute_id', $institute->id)
            ->where('is_current', true)
            ->first();

        if (!$fy) {
            return redirect()->route('finance.budgets.reports')->withErrors(['error' => 'No current fiscal year.']);
        }

        $comparison = $this->calc->budgetVsActual($institute->id, $branchId, $fy->id);

        $headers = ['Account Code', 'Account Name', 'Type', 'Budget', 'Actual', 'Variance', 'Variance %', 'Status'];
        $rows = $comparison['lines']->map(fn ($line) => [
            $line['code'],
            $line['name'],
            $line['type'],
            number_format($line['budget_amount'], 2),
            number_format($line['actual_amount'], 2),
            number_format($line['variance'], 2),
            $line['variance_pct'] . '%',
            $line['is_favorable'] ? 'Favorable' : 'Unfavorable',
        ]);

        return CsvStream::download(
            "budget-vs-actual-{$fy->name}-" . now()->format('Y-m-d') . '.csv',
            $headers,
            $rows
        );
    }
}
