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
     *
     * NULL BEHAVIOR (for future devs):
     * - Market ratios (EPS/BVPS) are null until institutes gains a
     *   shares_outstanding column (migration intentionally skipped).
     * - P/E + dividend payout are null (no market-price/dividend source).
     * - Interest-driven ratios are null (no interest-expense COA exists).
     * - OCF is an approximation (net income + depreciation); prefer
     *   FinancialReportService::cashFlowStatement() for audited cash flow.
     */
    public function computeAll(int $instituteId, string $asOfDate, ?string $fromDate = null, ?int $branchId = null): array
    {
        $from = $fromDate ?: now()->startOfYear()->format('Y-m-d');

        $balanceSheet = $this->getBalanceSheetData($instituteId, $asOfDate, $branchId);
        $income = $this->getIncomeStatementData($instituteId, $from, $asOfDate, $branchId);

        $liquidity = $this->liquidityRatios($balanceSheet);
        $profitability = $this->profitabilityRatios($balanceSheet, $income);
        $leverage = $this->leverageRatios($balanceSheet, $income);
        $efficiency = $this->efficiencyRatios($balanceSheet, $income);

        // Cash conversion cycle = DIO + DSO − DPO (days; null unless all known).
        $dio = $efficiency['days_inventory_outstanding'];
        $dso = $efficiency['days_sales_outstanding'];
        $dpo = $efficiency['days_payable_outstanding'];
        $liquidity['cash_conversion_cycle'] = ($dio !== null && $dso !== null && $dpo !== null)
            ? round($dio + $dso - $dpo, 1)
            : null;

        return [
            'as_of_date' => $asOfDate,
            'from_date' => $from,
            'liquidity' => $liquidity,
            'profitability' => $profitability,
            'leverage' => $leverage,
            'efficiency' => $efficiency,
            'market' => $this->marketRatios($income),
            'cash_flow' => $this->cashFlowRatios($instituteId, $balanceSheet, $income, $from, $asOfDate, $branchId),
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
            'cash' => ['1000.1', '1000.2'],
            'bank' => ['1100.1'],
            'current_assets' => ['1000.1', '1000.2', '1100.1', '1200.1', '1200.2', '1200.3', '1300.1', '1300.2', '1500.1', '1500.2'],
            'inventory' => ['1300.1', '1300.2'],
            'fixed_assets' => ['1400.1', '1400.2', '1400.3', '1400.4', '1400.5'],
            'total_assets' => ['1000.1', '1000.2', '1100.1', '1200.1', '1200.2', '1200.3', '1300.1', '1300.2', '1500.1', '1500.2', '1400.1', '1400.2', '1400.3', '1400.4', '1400.5'],
            'current_liab' => ['2000.1', '2000.2', '2000.3', '2100.1', '2100.2', '2100.3', '2100.4'],
            'total_liab' => ['2000.1', '2000.2', '2000.3', '2100.1', '2100.2', '2100.3', '2100.4', '2200.1', '2200.2', '2200.3'],
            'total_equity' => ['3100.1', '3100.2', '3300.1', '3300.2', '3300.3', '3300.4', '3400.1', '3400.2'],
            'receivables' => ['1200.1'],
            'payables' => ['2000.1'],
            'long_term_debt' => ['2200.2'],
        ];

        $debitNature = ['cash', 'bank', 'current_assets', 'inventory', 'fixed_assets', 'total_assets', 'receivables'];

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
        $cogsIds = $this->globalIds(['5000.5']);

        [$revDr, $revCr] = $this->sums($instituteId, $revenueIds, $from, $to, $branchId);
        [$cogsDr] = $this->sums($instituteId, $cogsIds, $from, $to, $branchId);
        [$expDr, $expCr] = $this->sums($instituteId, $expenseIds, $from, $to, $branchId);

        // Interest expense (no interest COA exists today → always 0, ratios null-safe).
        $interestIds = ChartOfAccount::withoutGlobalScope('institute')
            ->whereNull('institute_id')
            ->where(function ($q) {
                $q->where('name', 'like', '%interest%')
                    ->orWhere('name', 'like', '%finance cost%');
            })->pluck('id');
        [$intDr, $intCr] = $this->sums($instituteId, $interestIds, $from, $to, $branchId);

        // Depreciation from 5400.1.
        [$depDr] = $this->sums($instituteId, $this->globalIds(['5400.1']), $from, $to, $branchId);

        $revenue = $revCr - $revDr;
        $expense = $expDr - $expCr;
        $interest = $intDr - $intCr;
        $netIncome = $revenue - $expense;
        $ebit = $netIncome + $interest;
        $ebitda = $ebit + $depDr;
        $operatingIncome = $revenue - $cogsDr - ($expense - $interest - $depDr);

        return [
            'revenue' => $revenue,
            'cogs' => $cogsDr,
            'expense' => $expense,
            'interest' => $interest,
            'depreciation' => $depDr,
            'ebit' => $ebit,
            'ebitda' => $ebitda,
            'operating_income' => $operatingIncome,
            'net_income' => $netIncome,
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
            'net_working_capital_ratio' => $this->safeDiv(
                $bs['current_assets'] - $bs['current_liab'],
                $bs['total_assets']
            ),
        ];
    }

    protected function profitabilityRatios(array $bs, array $is): array
    {
        return [
            'gross_profit_margin' => $this->safeDiv($is['revenue'] - $is['cogs'], $is['revenue']),
            'operating_profit_margin' => $this->safeDiv($is['operating_income'], $is['revenue']),
            'ebitda_margin' => $this->safeDiv($is['ebitda'], $is['revenue']),
            'net_profit_margin' => $this->safeDiv($is['net_income'], $is['revenue']),
            'roa' => $this->safeDiv($is['net_income'], $bs['total_assets']),
            'roe' => $this->safeDiv($is['net_income'], $bs['total_equity']),
            'roce' => $this->safeDiv($is['ebit'], $bs['total_assets'] - $bs['current_liab']),
        ];
    }

    protected function leverageRatios(array $bs, array $is): array
    {
        return [
            'debt_to_equity' => $this->safeDiv($bs['total_liab'], $bs['total_equity']),
            'debt_ratio' => $this->safeDiv($bs['total_liab'], $bs['total_assets']),
            'equity_ratio' => $this->safeDiv($bs['total_equity'], $bs['total_assets']),
            'proprietary_ratio' => $this->safeDiv($bs['total_equity'], $bs['total_assets']),
            'interest_coverage' => $this->safeDiv($is['ebit'], $is['interest']),
            'debt_service_coverage' => $this->safeDiv(
                $is['operating_income'],
                $is['interest'] + ($bs['long_term_debt'] ?? 0) * 0.1
            ),
        ];
    }

    protected function efficiencyRatios(array $bs, array $is): array
    {
        $assetTurnover = $this->safeDiv($is['revenue'], $bs['total_assets']);
        $receivableTurnover = $this->safeDiv($is['revenue'], $bs['receivables']);
        $payableTurnover = $this->safeDiv($is['cogs'], $bs['payables']);
        $inventoryTurnover = $this->safeDiv($is['cogs'], $bs['inventory']);
        $fixedAssetTurnover = $this->safeDiv($is['revenue'], $bs['fixed_assets']);

        return [
            'asset_turnover' => $assetTurnover,
            'fixed_asset_turnover' => $fixedAssetTurnover,
            'inventory_turnover' => $inventoryTurnover,
            'receivable_turnover' => $receivableTurnover,
            'payable_turnover' => $payableTurnover,
            'days_inventory_outstanding' => $inventoryTurnover ? round(365 / $inventoryTurnover, 1) : null,
            'days_sales_outstanding' => $receivableTurnover ? round(365 / $receivableTurnover, 1) : null,
            'days_payable_outstanding' => $payableTurnover ? round(365 / $payableTurnover, 1) : null,
        ];
    }

    /**
     * Market/investor ratios. All null until a shares_outstanding source
     * exists (migration intentionally skipped) — graceful by design.
     */
    protected function marketRatios(array $is): array
    {
        return [
            'earnings_per_share' => null,
            'book_value_per_share' => null,
            'pe_ratio' => null,
            'dividend_payout_ratio' => null,
        ];
    }

    /**
     * Cash-flow ratios. OCF is an approximation (net income + depreciation);
     * prefer FinancialReportService::cashFlowStatement() for audited figures.
     */
    protected function cashFlowRatios(int $instituteId, array $bs, array $is, string $from, string $to, ?int $branchId): array
    {
        $ocf = $is['net_income'] + $is['depreciation'];

        [$capexDr, $capexCr] = $this->sums($instituteId, $this->globalIds(['1400.1', '1400.2', '1400.3', '1400.4']), $from, $to, $branchId);
        $fcf = $ocf - ($capexDr - $capexCr);

        return [
            'operating_cash_flow_ratio' => $this->safeDiv($ocf, $bs['current_liab']),
            'free_cash_flow' => $fcf,
            'cash_flow_margin' => $this->safeDiv($ocf, $is['revenue']),
        ];
    }
}
