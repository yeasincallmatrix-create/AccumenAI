<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * STEP 11 — Accounting Reports & Financial Statements.
 *
 * Thin controllers over AccountingReportService. Every report resolves the
 * institute from the authenticated user/workspace (never from request input),
 * is scoped to the acting branch, and requires the existing
 * reports.financial.view permission (route middleware). All figures are
 * read-only derivations from posted journals + opening balances.
 */
class AccountingReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly AccountingReportService $reports,
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

    public function generalLedger(Request $request): View
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
            'fiscalYears' => $this->fiscalYears($institute->id),
            'fiscalYearId' => $this->fiscalYearId($request),
        ]);
    }

    public function accountLedger(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from');
        $to = $request->query('to');
        $coaId = $request->query('account_id') ? (int) $request->query('account_id') : null;

        $statement = $coaId !== null
            ? $this->reports->accountLedger($institute->id, $branchId, $coaId, $from, $to, $this->fiscalYearId($request))
            : null;

        return view('institute.finance.reports.account-ledger', [
            'institute' => $institute,
            'statement' => $statement,
            'accounts' => ChartOfAccount::query()
                ->where('institute_id', $institute->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
            'accountId' => $coaId,
            'from' => $from,
            'to' => $to,
            'fiscalYears' => $this->fiscalYears($institute->id),
            'fiscalYearId' => $this->fiscalYearId($request),
        ]);
    }

    public function profitAndLoss(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $year = (int) now()->format('Y');
        $from = $request->query('from') ?: "{$year}-01-01";
        $to = $request->query('to') ?: now()->toDateString();

        return view('institute.finance.reports.profit-loss', [
            'institute' => $institute,
            'statement' => $this->reports->profitAndLoss($institute->id, $branchId, $from, $to),
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

    public function cashFlow(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $year = (int) now()->format('Y');
        $from = $request->query('from') ?: "{$year}-01-01";
        $to = $request->query('to') ?: now()->toDateString();

        return view('institute.finance.reports.cash-flow', [
            'institute' => $institute,
            'statement' => $this->reports->cashFlowStatement($institute->id, $branchId, $from, $to, $this->fiscalYearId($request)),
            'from' => $from,
            'to' => $to,
            'fiscalYears' => $this->fiscalYears($institute->id),
            'fiscalYearId' => $this->fiscalYearId($request),
        ]);
    }

    public function receivables(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        $report = $this->reports->receivablesReport($institute->id, $branchId, $asOf);

        return view('institute.finance.reports.receivables', [
            'institute' => $institute,
            'customers' => $report['customers'],
            'totals' => $report['totals'],
            'asOf' => $asOf,
        ]);
    }

    public function payables(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        $report = $this->reports->payablesReport($institute->id, $branchId, $asOf);

        return view('institute.finance.reports.payables', [
            'institute' => $institute,
            'suppliers' => $report['suppliers'],
            'totals' => $report['totals'],
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
