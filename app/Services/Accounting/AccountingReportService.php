<?php

namespace App\Services\Accounting;

use Illuminate\Support\Collection;

/**
 * STEP 11 — Accounting Reports & Financial Statements.
 *
 * Read-only report facade over the existing ledger services. All figures come
 * from posted journal entries (journals.status = 'posted', reversal_of IS NULL,
 * not soft-deleted) plus per-fiscal-year opening balances — there are no
 * duplicated balance tables and nothing is ever written here.
 *
 * This service composes the existing FinancialReportService (trial balance,
 * income statement / profit & loss, balance sheet, general ledger, account
 * ledger, cash & bank) and the existing ReceivablesPayablesService (derived
 * AR/AP with aging). It does NOT reimplement any of their queries.
 */
class AccountingReportService
{
    public function __construct(
        private readonly FinancialReportService $reports,
        private readonly ReceivablesPayablesService $arp,
    ) {}

    /**
     * Trial balance as of a date (opening balances + postings).
     *
     * @return Collection<int, object>
     */
    public function trialBalance(int $instituteId, ?int $branchId, ?string $asOfDate = null, ?int $fiscalYearId = null): Collection
    {
        return $this->reports->trialBalance($instituteId, $branchId, $asOfDate, $fiscalYearId);
    }

    /**
     * General ledger lines for one account (or all accounts when coaId is null).
     *
     * @return Collection<int, object>
     */
    public function generalLedger(int $instituteId, ?int $branchId, ?int $coaId = null, ?string $from = null, ?string $to = null, ?int $fiscalYearId = null): Collection
    {
        return $this->reports->generalLedger($instituteId, $branchId, $coaId, $from, $to, $fiscalYearId);
    }

    /**
     * Account ledger: one account's activity with opening/closing balances.
     *
     * @return array{account: object, opening: float, lines: Collection<int, object>, debit: float, credit: float, closing: float}
     */
    public function accountLedger(int $instituteId, ?int $branchId, int $coaId, ?string $from = null, ?string $to = null, ?int $fiscalYearId = null): array
    {
        return $this->reports->accountLedger($instituteId, $branchId, $coaId, $from, $to, $fiscalYearId);
    }

    /**
     * Profit & loss (income statement) over a date range.
     *
     * @return array{income: Collection<int, object>, expense: Collection<int, object>, total_income: float, total_expense: float, net: float}
     */
    public function profitAndLoss(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        return $this->reports->incomeStatement($instituteId, $branchId, $from, $to);
    }

    /**
     * Balance sheet as of a date. Current-period net income is folded into
     * equity.
     *
     * @return array{assets: Collection<int, object>, liabilities: Collection<int, object>, equity: Collection<int, object>, total_assets: float, total_liabilities: float, total_equity: float, net_income: float}
     */
    public function balanceSheet(int $instituteId, ?int $branchId, ?string $asOfDate = null, ?int $fiscalYearId = null): array
    {
        return $this->reports->balanceSheet($instituteId, $branchId, $asOfDate, $fiscalYearId);
    }

    /**
     * Cash & bank account balances (asset accounts flagged is_cash/is_bank).
     *
     * @return Collection<int, object>
     */
    public function cashBankSummary(int $instituteId, ?int $branchId, ?string $asOfDate = null, ?int $fiscalYearId = null): Collection
    {
        return $this->reports->cashBankSummary($instituteId, $branchId, $asOfDate, $fiscalYearId);
    }

    /**
     * Cash flow statement (direct method) over a date range.
     *
     * @return array{operating: float, investing: float, financing: float, net_change: float, opening: float, closing: float, unclassified_amount: float}
     */
    public function cashFlowStatement(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null, ?int $fiscalYearId = null): array
    {
        return $this->reports->cashFlowStatement($instituteId, $branchId, $from, $to, $fiscalYearId);
    }

    /**
     * Receivables report: customer balances with aging buckets + totals.
     *
     * @return array{customers: Collection<int, object>, totals: array{receivable: float, payable: float, net: float}}
     */
    public function receivablesReport(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): array
    {
        return [
            'customers' => $this->arp->customerBalancesWithAging($instituteId, $branchId, $asOfDate),
            'totals' => $this->arp->totals($instituteId, $branchId, $asOfDate),
        ];
    }

    /**
     * Payables report: supplier balances with aging buckets + totals.
     *
     * @return array{suppliers: Collection<int, object>, totals: array{receivable: float, payable: float, net: float}}
     */
    public function payablesReport(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): array
    {
        return [
            'suppliers' => $this->arp->supplierBalancesWithAging($instituteId, $branchId, $asOfDate),
            'totals' => $this->arp->totals($instituteId, $branchId, $asOfDate),
        ];
    }
}
