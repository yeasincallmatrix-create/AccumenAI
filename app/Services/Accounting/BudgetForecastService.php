<?php

namespace App\Services\Accounting;

use App\Models\Budget;
use App\Models\FiscalYear;
use Illuminate\Support\Carbon;

class BudgetForecastService
{
    public function __construct(
        private readonly BudgetCalculationService $calc,
        private readonly FinancialReportService $reports,
    ) {}

    public function forecast(int $instituteId, ?int $branchId, int $fiscalYearId): array
    {
        $budget = Budget::where('institute_id', $instituteId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('status', ['approved', 'locked'])
            ->first();

        $year = FiscalYear::findOrFail($fiscalYearId);
        $today = Carbon::today();
        $startOfYear = Carbon::parse($year->start_date);
        $endOfYear = Carbon::parse($year->end_date);

        $totalDays = max($startOfYear->diffInDays($endOfYear), 1);
        $elapsedDays = max($startOfYear->diffInDays($today), 0);
        $progressPct = min(round(($elapsedDays / $totalDays) * 100, 2), 100);

        $incomeStmt = $this->reports->incomeStatement(
            $instituteId,
            $branchId,
            $year->start_date,
            min($today->toDateString(), $year->end_date)
        );

        $actualIncome = $incomeStmt['total_income'] ?? 0;
        $actualExpense = $incomeStmt['total_expense'] ?? 0;

        $budgetTotal = $budget?->total_amount ?? 0;

        $forecastIncome = $progressPct > 0 ? round(($actualIncome / $progressPct) * 100, 2) : 0;
        $forecastExpense = $progressPct > 0 ? round(($actualExpense / $progressPct) * 100, 2) : 0;

        $remainingDays = max($endOfYear->diffInDays($today), 0);
        $remainingIncome = max($forecastIncome - $actualIncome, 0);
        $remainingExpense = max($forecastExpense - $actualExpense, 0);

        $yearEndVariance = $budget ? ($budgetTotal - $forecastExpense) : 0;

        return [
            'budget_total' => $budgetTotal,
            'actual_income' => $actualIncome,
            'actual_expense' => $actualExpense,
            'forecast_income' => $forecastIncome,
            'forecast_expense' => $forecastExpense,
            'remaining_income' => $remainingIncome,
            'remaining_expense' => $remainingExpense,
            'progress_pct' => $progressPct,
            'elapsed_days' => $elapsedDays,
            'total_days' => $totalDays,
            'remaining_days' => $remainingDays,
            'year_end_variance' => $yearEndVariance,
            'fiscal_year' => $year,
        ];
    }

    public function cashFlowPlan(int $instituteId, ?int $branchId, int $fiscalYearId): array
    {
        $year = FiscalYear::with('periods')->findOrFail($fiscalYearId);
        $today = Carbon::today();

        $monthly = collect();
        $cumulativeIncome = 0;
        $cumulativeExpense = 0;

        foreach ($year->periods->sortBy('start_date') as $period) {
            $periodEnd = Carbon::parse($period->end_date);
            $isPast = $periodEnd->lte($today);

            $incomeStmt = $this->reports->incomeStatement(
                $instituteId,
                $branchId,
                $period->start_date,
                $period->end_date
            );

            $income = $incomeStmt['total_income'] ?? 0;
            $expense = $incomeStmt['total_expense'] ?? 0;
            $cumulativeIncome += $income;
            $cumulativeExpense += $expense;

            $monthly->push([
                'period' => $period->name,
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
                'income' => $income,
                'expense' => $expense,
                'net_cash_flow' => $income - $expense,
                'cumulative_income' => $cumulativeIncome,
                'cumulative_expense' => $cumulativeExpense,
                'cumulative_net' => $cumulativeIncome - $cumulativeExpense,
                'is_actual' => $isPast,
            ]);
        }

        $totalIncome = $monthly->sum('income');
        $totalExpense = $monthly->sum('expense');

        return [
            'monthly' => $monthly,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_cash_flow' => $totalIncome - $totalExpense,
        ];
    }
}
