<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\Payment;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\ReceivablesPayablesService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Finance dashboard (Step 32 / Step 9): headline cash/AR/AP figures, the
 * current fiscal year & period, income/expense and recent journal/payment
 * activity for the acting institute (branch-scoped automatically).
 */
class FinanceDashboardController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly FinancialReportService $reports,
        private readonly ReceivablesPayablesService $arp,
        private readonly AccountingSetupService $settings,
        private readonly AccountingPeriodService $periods,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $year = (int) now()->format('Y');
        $cashBank = $this->reports->cashBankSummary($institute->id, $branchId);
        $arpTotals = $this->arp->totals($institute->id, $branchId);
        $income = $this->reports->incomeStatement($institute->id, $branchId, "{$year}-01-01", now()->toDateString());
        $currentPeriod = $this->periods->current($institute->id, $branchId);

        $journals = Journal::query()
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')));

        $payments = Payment::query()
            ->with('invoice.party')
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')));

        return view('institute.finance.dashboard', [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
            'cashBank' => $cashBank,
            'cashTotal' => $cashBank->where('is_cash', true)->sum('balance'),
            'bankTotal' => $cashBank->where('is_bank', true)->sum('balance'),
            'arpTotals' => $arpTotals,
            'incomeStatement' => $income,
            'netIncome' => $income['net'],
            'journalCount' => (clone $journals)->count(),
            'recentJournals' => (clone $journals)
                ->with(['creator', 'period'])
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'recentPayments' => (clone $payments)
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'accountsCount' => ChartOfAccount::query()
                ->where('institute_id', $institute->id)
                ->where('is_active', true)
                ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id')))
                ->count(),
            'currentFiscalYear' => $currentPeriod['year'],
            'currentPeriod' => $currentPeriod['period'],
            'baseCurrency' => $this->settings->getSetting($institute->id, 'base_currency', 'USD', $branchId),
        ]);
    }
}
