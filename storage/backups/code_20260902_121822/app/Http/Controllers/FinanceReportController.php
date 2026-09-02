<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\ReceivablesPayablesService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Financial reports (Step 32): trial balance, income statement, balance sheet,
 * general ledger, cash/bank summary, receivables and payables — all derived
 * from posted journals + opening balances (no duplicated balance tables).
 */
class FinanceReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly FinancialReportService $reports,
        private readonly ReceivablesPayablesService $arp,
    ) {}

    public function trialBalance(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        return view('institute.finance.reports.trial-balance', [
            'institute' => $institute,
            'rows' => $this->reports->trialBalance($institute->id, $branchId, $asOf, $this->fiscalYearId($request)),
            'asOf' => $asOf,
            'fiscalYears' => $this->fiscalYears($institute->id),
            'fiscalYearId' => $this->fiscalYearId($request),
        ]);
    }

    public function incomeStatement(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $year = (int) now()->format('Y');
        $from = $request->query('from') ?: "{$year}-01-01";
        $to = $request->query('to') ?: now()->toDateString();

        return view('institute.finance.reports.income-statement', [
            'institute' => $institute,
            'statement' => $this->reports->incomeStatement($institute->id, $branchId, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function balanceSheet(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        return view('institute.finance.reports.balance-sheet', [
            'institute' => $institute,
            'statement' => $this->reports->balanceSheet($institute->id, $branchId, $asOf, $this->fiscalYearId($request)),
            'asOf' => $asOf,
            'fiscalYears' => $this->fiscalYears($institute->id),
            'fiscalYearId' => $this->fiscalYearId($request),
        ]);
    }

    public function ledger(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from');
        $to = $request->query('to');
        $coaId = $request->query('account_id') ? (int) $request->query('account_id') : null;

        return view('institute.finance.reports.ledger', [
            'institute' => $institute,
            'ledger' => $this->reports->generalLedger($institute->id, $branchId, $coaId, $from, $to, $this->fiscalYearId($request)),
            'accounts' => ChartOfAccount::query()
                ->where('institute_id', $institute->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
            'accountId' => $coaId,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function cashBank(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        return view('institute.finance.reports.cash-bank', [
            'institute' => $institute,
            'rows' => $this->reports->cashBankSummary($institute->id, $branchId, $asOf, $this->fiscalYearId($request)),
            'asOf' => $asOf,
            'fiscalYears' => $this->fiscalYears($institute->id),
            'fiscalYearId' => $this->fiscalYearId($request),
        ]);
    }

    public function receivables(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        return view('institute.finance.reports.receivables', [
            'institute' => $institute,
            'customers' => $this->arp->customerBalancesWithAging($institute->id, $branchId, $asOf),
            'totals' => $this->arp->totals($institute->id, $branchId, $asOf),
            'asOf' => $asOf,
        ]);
    }

    public function payables(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        return view('institute.finance.reports.payables', [
            'institute' => $institute,
            'suppliers' => $this->arp->supplierBalancesWithAging($institute->id, $branchId, $asOf),
            'totals' => $this->arp->totals($institute->id, $branchId, $asOf),
            'asOf' => $asOf,
        ]);
    }

    // ------------------------------------------------------------- Internals

    private function fiscalYears(int $instituteId): Collection
    {
        return FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->orderByDesc('start_date')
            ->get(['id', 'name']);
    }

    private function fiscalYearId(Request $request): ?int
    {
        return $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null;
    }
}
