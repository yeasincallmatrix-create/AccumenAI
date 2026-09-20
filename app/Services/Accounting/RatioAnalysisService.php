<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class RatioAnalysisService
{
    /**
     * Compute all ratios for a tenant + date range.
     *
     * All figures are read-only derivations from posted journals.
     * Zero-division safe: null means "not computable".
     */
    public function computeAll(int $instituteId, string $asOfDate, ?string $fromDate = null, ?int $branchId = null): array
    {
        $from = $fromDate ?: now()->startOfYear()->format('Y-m-d');

        $balanceSheet = $this->getBalanceSheetData($instituteId, $asOfDate, $branchId);
        $income = $this->getIncomeStatementData($instituteId, $from, $asOfDate, $branchId);

        return [
            'as_of_date' => $asOfDate,
            'from_date' => $from,
            'liquidity' => $this->liquidityRatios($balanceSheet),
            'profitability' => $this->profitabilityRatios($balanceSheet, $income),
            'leverage' => $this->leverageRatios($balanceSheet, $income),
            'efficiency' => $this->efficiencyRatios($balanceSheet, $income),
        ];
    }

    protected function globalIds(array $codes): \Illuminate\Support\Collection
    {
        return ChartOfAccount::withoutGlobalScope('institute')
            ->whereNull('institute_id')
            ->whereIn('code', $codes)
            ->pluck('id');
    }

    protected function sums(int $instituteId, $coaIds, string $from, string $to, ?int $branchId, bool $asOf = false): array
    {
        $debit = DB::table('journal_entries')
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('coa_id', $coaIds)
            ->when($asOf, fn ($q) => $q->whereDate('journal_date', '<=', $to),
                fn ($q) => $q->whereBetween('journal_date', [$from, $to]))
            ->sum('debit');

        $credit = DB::table('journal_entries')
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('coa_id', $coaIds)
            ->when($asOf, fn ($q) => $q->whereDate('journal_date', '<=', $to),
                fn ($q) => $q->whereBetween('journal_date', [$from, $to]))
            ->sum('credit');

        return [(float) $debit, (float) $credit];
    }

    protected function getBalanceSheetData(int $instituteId, string $asOf, ?int $branchId): array
    {
        $groups = [
            'cash' => ['1000'],
            'bank' => ['1100'],
            'current_assets' => ['1000', '1100', '1200', '1300', '1400'],
            'inventory' => ['1300'],
            'fixed_assets' => ['1500'],
            'total_assets' => ['1000', '1100', '1200', '1300', '1400', '1500'],
            'current_liab' => ['2000', '2100'],
            'total_liab' => ['2000', '2100'],
            'total_equity' => ['3000', '3001', '3002', '3100'],
        ];

        $debitNature = ['cash', 'bank', 'current_assets', 'inventory', 'fixed_assets', 'total_assets'];

        $result = [];
        foreach ($groups as $key => $codes) {
            [$debit, $credit] = $this->sums($instituteId, $this->globalIds($codes), '', $asOf, $branchId, true);
            $result[$key] = in_array($key, $debitNature, true) ? $debit - $credit : $credit - $debit;
        }

        return $result;
    }

    protected function getIncomeStatementData(int $instituteId, string $from, string $to, ?int $branchId): array
    {
        $revenueIds = ChartOfAccount::withoutGlobalScope('institute')
            ->whereNull('institute_id')->where('type', 'income')->pluck('id');
        $expenseIds = ChartOfAccount::withoutGlobalScope('institute')
            ->whereNull('institute_id')->where('type', 'expense')->pluck('id');
        $cogsIds = $this->globalIds(['5007']);

        [$revDr, $revCr] = $this->sums($instituteId, $revenueIds, $from, $to, $branchId);
        [$cogsDr] = $this->sums($instituteId, $cogsIds, $from, $to, $branchId);
        [$expDr, $expCr] = $this->sums($instituteId, $expenseIds, $from, $to, $branchId);

        $revenue = $revCr - $revDr;
        $expense = $expDr - $expCr;

        return [
            'revenue' => $revenue,
            'cogs' => $cogsDr,
            'expense' => $expense,
            'net_income' => $revenue - $expense,
        ];
    }

    protected function safeDiv($a, $b): ?float
    {
        if ($b == 0) {
            return null;
        }

        return round($a / $b, 4);
    }

    protected function liquidityRatios(array $bs): array
    {
        return [
            'current_ratio' => $this->safeDiv($bs['current_assets'], $bs['current_liab']),
            'quick_ratio' => $this->safeDiv($bs['current_assets'] - $bs['inventory'], $bs['current_liab']),
            'cash_ratio' => $this->safeDiv($bs['cash'] + $bs['bank'], $bs['current_liab']),
            'working_capital' => $bs['current_assets'] - $bs['current_liab'],
        ];
    }

    protected function profitabilityRatios(array $bs, array $is): array
    {
        return [
            'gross_profit_margin' => $this->safeDiv($is['revenue'] - $is['cogs'], $is['revenue']),
            'net_profit_margin' => $this->safeDiv($is['net_income'], $is['revenue']),
            'roa' => $this->safeDiv($is['net_income'], $bs['total_assets']),
            'roe' => $this->safeDiv($is['net_income'], $bs['total_equity']),
        ];
    }

    protected function leverageRatios(array $bs, array $is): array
    {
        return [
            'debt_to_equity' => $this->safeDiv($bs['total_liab'], $bs['total_equity']),
            'debt_ratio' => $this->safeDiv($bs['total_liab'], $bs['total_assets']),
            'equity_ratio' => $this->safeDiv($bs['total_equity'], $bs['total_assets']),
        ];
    }

    protected function efficiencyRatios(array $bs, array $is): array
    {
        return [
            'asset_turnover' => $this->safeDiv($is['revenue'], $bs['total_assets']),
            'inventory_turnover' => $this->safeDiv($is['cogs'], $bs['inventory']),
        ];
    }
}
