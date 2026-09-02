<?php

namespace App\Services\Accounting;

use App\Models\Budget;
use App\Models\BudgetLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BudgetCalculationService
{
    public function __construct(
        private readonly FinancialReportService $reports,
    ) {}

    public function budgetVsActual(int $instituteId, ?int $branchId, int $fiscalYearId): array
    {
        $budget = Budget::where('institute_id', $instituteId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('status', 'approved')
            ->orWhere('status', 'locked')
            ->first();

        if (!$budget) {
            return ['lines' => collect(), 'totals' => $this->emptyTotals()];
        }

        return $this->computeComparison($instituteId, $branchId, $budget);
    }

    public function budgetVsActualForBudget(int $instituteId, ?int $branchId, int $budgetId): array
    {
        $budget = Budget::with('versions.lines.account')
            ->where('institute_id', $instituteId)
            ->where('id', $budgetId)
            ->firstOrFail();

        return $this->computeComparison($instituteId, $branchId, $budget);
    }

    public function monthlyActuals(int $instituteId, ?int $branchId, int $fiscalYearId): Collection
    {
        $year = \App\Models\FiscalYear::with('periods')->findOrFail($fiscalYearId);
        $results = collect();

        foreach ($year->periods->sortBy('start_date') as $period) {
            $income = $this->reports->incomeStatement(
                $instituteId,
                $branchId,
                $period->start_date,
                $period->end_date
            );
            $results->push([
                'period' => $period->name,
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
                'income' => $income['total_income'] ?? 0,
                'expense' => $income['total_expense'] ?? 0,
                'net' => $income['net'] ?? 0,
            ]);
        }

        return $results;
    }

    public function varianceAnalysis(int $instituteId, ?int $branchId, int $fiscalYearId): array
    {
        $comparison = $this->budgetVsActual($instituteId, $branchId, $fiscalYearId);
        $lines = $comparison['lines'];

        $overBudget = $lines->filter(fn ($line) => $line['variance'] < 0 && $line['budget_amount'] > 0)
            ->sortBy('variance_pct')
            ->values();

        $underBudget = $lines->filter(fn ($line) => $line['variance'] > 0 && $line['budget_amount'] > 0)
            ->sortByDesc('variance_pct')
            ->values();

        return [
            'over_budget' => $overBudget,
            'under_budget' => $underBudget,
            'totals' => $comparison['totals'],
        ];
    }

    private function computeComparison(int $instituteId, ?int $branchId, Budget $budget): array
    {
        $version = $budget->versions()->where('version', $budget->version)->first();
        if (!$version) {
            return ['lines' => collect(), 'totals' => $this->emptyTotals()];
        }

        $lines = $version->lines()->with('account', 'period')->get();

        $year = $budget->fiscalYear;
        $from = $year->start_date;
        $to = $year->end_date;

        $periodIds = $lines->where('month', '>', 0)->pluck('period_id')->filter()->unique()->values()->all();
        $hasPeriodLines = count($periodIds) > 0;

        if ($hasPeriodLines) {
            $periods = \App\Models\AccountingPeriod::whereIn('id', $periodIds)->get()->keyBy('id');
            $earliestPeriod = $periods->min('start_date');
            $latestPeriod = $periods->max('end_date');
            if ($earliestPeriod) $from = $earliestPeriod;
            if ($latestPeriod) $to = $latestPeriod;
        }

        $incomeStmt = $this->reports->incomeStatement($instituteId, $branchId, $from, $to);
        $incomeByCoa = collect($incomeStmt['income'] ?? [])->keyBy('coa_id');
        $expenseByCoa = collect($incomeStmt['expense'] ?? [])->keyBy('coa_id');

        $nonIsAccountIds = $lines->reject(fn ($l) => in_array($l->account?->type, ['income', 'expense']))
            ->pluck('coa_id')->unique()->values()->all();
        $ledgerBalances = [];
        if (count($nonIsAccountIds) > 0) {
            foreach ($nonIsAccountIds as $coaId) {
                $ledger = $this->reports->generalLedger($instituteId, $branchId, $coaId, $from, $to);
                $type = $lines->firstWhere('coa_id', $coaId)?->account?->type;
                $debit = $ledger->sum('debit');
                $credit = $ledger->sum('credit');
                $ledgerBalances[$coaId] = $type === 'asset' ? ($debit - $credit) : ($credit - $debit);
            }
        }

        $resultLines = collect();
        $totalBudget = 0;
        $totalActual = 0;

        foreach ($lines as $line) {
            $actual = $this->resolveActual($line, $incomeByCoa, $expenseByCoa, $ledgerBalances);

            $budgetAmount = (float) $line->amount;
            $variance = $budgetAmount - $actual;
            $variancePct = $budgetAmount != 0 ? round(($variance / $budgetAmount) * 100, 2) : 0;

            $resultLines->push([
                'coa_id' => $line->coa_id,
                'code' => $line->account?->code ?? '',
                'name' => $line->account?->name ?? '',
                'type' => $line->account?->type ?? '',
                'month' => $line->month,
                'budget_amount' => $budgetAmount,
                'actual_amount' => $actual,
                'variance' => $variance,
                'variance_pct' => $variancePct,
                'is_favorable' => $this->isFavorable($line->account?->type, $variance),
            ]);

            $totalBudget += $budgetAmount;
            $totalActual += $actual;
        }

        $totalVariance = $totalBudget - $totalActual;
        $totalVariancePct = $totalBudget != 0 ? round(($totalVariance / $totalBudget) * 100, 2) : 0;

        return [
            'budget' => $budget,
            'lines' => $resultLines,
            'totals' => [
                'budget' => $totalBudget,
                'actual' => $totalActual,
                'variance' => $totalVariance,
                'variance_pct' => $totalVariancePct,
            ],
        ];
    }

    private function resolveActual(BudgetLine $line, Collection $incomeByCoa, Collection $expenseByCoa, array $ledgerBalances): float
    {
        $accountType = $line->account?->type;

        if ($accountType === 'income') {
            return (float) ($incomeByCoa->get($line->coa_id)?->balance ?? 0);
        }
        if ($accountType === 'expense') {
            return (float) ($expenseByCoa->get($line->coa_id)?->balance ?? 0);
        }

        return $ledgerBalances[$line->coa_id] ?? 0;
    }

    private function isFavorable(?string $accountType, float $variance): bool
    {
        if (in_array($accountType, ['income'])) {
            return $variance >= 0;
        }
        return $variance >= 0;
    }

    private function emptyTotals(): array
    {
        return ['budget' => 0, 'actual' => 0, 'variance' => 0, 'variance_pct' => 0];
    }
}
