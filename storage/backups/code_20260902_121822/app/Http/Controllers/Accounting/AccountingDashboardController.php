<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingDashboardService;
use App\Services\Accounting\AccountingSetupService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * STEP 13 — Accounting Dashboard.
 *
 * Thin controller: resolves the institute and acting branch from the
 * authenticated user/workspace (never from request input), validates the
 * requested branch/fiscal-year/date filters against authorized values, and
 * hands the rest to AccountingDashboardService. All figures come from the
 * existing STEP 5/11/12 reporting services.
 */
class AccountingDashboardController extends Controller
{
    use ResolvesInstitute;

    private const PRESETS = ['this_month', 'last_month', 'this_quarter', 'fiscal_year', 'previous_fiscal_year', 'custom'];

    public function __construct(
        private readonly AccountingDashboardService $dashboard,
        private readonly AccountingSetupService $settings,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $actingBranchId = $this->actingBranchId($request);
        $branchId = $actingBranchId;
        $branches = collect();

        if ($actingBranchId === null) {
            // Whole-institute users may filter by any active branch; the id is
            // validated against the institute's branches below (no IDOR).
            $branches = Branch::query()
                ->where('institute_id', $institute->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']);

            $requestedBranch = $request->query('branch_id');
            if ($requestedBranch !== null && $branches->contains('id', (int) $requestedBranch)) {
                $branchId = (int) $requestedBranch;
            }
        }

        $fiscalYears = FiscalYear::query()
            ->where('institute_id', $institute->id)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date', 'end_date']);

        $requestedYear = $request->query('fiscal_year_id');
        $fiscalYearId = $requestedYear !== null && $fiscalYears->contains('id', (int) $requestedYear)
            ? (int) $requestedYear
            : null;

        $preset = in_array($request->query('range'), self::PRESETS, true) ? $request->query('range') : 'fiscal_year';

        [$from, $to] = $this->resolveRange($preset, $request, $fiscalYears, $fiscalYearId, Carbon::today());

        $data = $this->dashboard->summary((int) $institute->id, $branchId, $from, $to, $fiscalYearId);

        return view('institute.accounting.dashboard', [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
            'branches' => $branches,
            'branchId' => $branchId,
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'preset' => $preset,
            'presets' => self::PRESETS,
            'range' => ['from' => $from, 'to' => $to],
            'baseCurrency' => $this->settings->getSetting((int) $institute->id, 'base_currency', 'USD', $branchId),
            'summary' => $data['summary'],
            'receivableTotal' => $data['receivables'],
            'payableTotal' => $data['payables'],
            'cash' => $data['cash'],
            'arpAging' => $data['arp_aging'],
            'monthly' => $data['monthly'],
            'topAccounts' => $data['top_accounts'],
            'recentJournals' => $data['recent_journals'],
            'periodStatus' => $data['period_status'],
            'budgetUtilization' => $data['budget_utilization'],
            'pendingApprovals' => $data['pending_approvals'],
        ]);
    }

    /**
     * Resolve a validated [from, to] date range. Every preset is computed
     * server-side; a custom range must be two well-formed Y-m-d dates with
     * from <= to, otherwise the fiscal-year default is used.
     */
    private function resolveRange(string $preset, Request $request, Collection $fiscalYears, ?int $fiscalYearId, Carbon $today): array
    {
        switch ($preset) {
            case 'this_month':
                return [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()];

            case 'last_month':
                $last = $today->copy()->subMonthNoOverflow();

                return [$last->copy()->startOfMonth()->toDateString(), $last->copy()->endOfMonth()->toDateString()];

            case 'this_quarter':
                return [$today->copy()->startOfQuarter()->toDateString(), $today->toDateString()];

            case 'previous_fiscal_year':
                $current = $this->currentFiscalYear($fiscalYears, $today);
                $previous = $current !== null
                    ? $fiscalYears->filter(fn (FiscalYear $fy) => $fy->start_date->lt($current->start_date))
                        ->sortByDesc('start_date')
                        ->first()
                    : null;

                if ($previous !== null) {
                    return [$previous->start_date->toDateString(), $previous->end_date->toDateString()];
                }
                break;

            case 'custom':
                $from = $request->query('from');
                $to = $request->query('to');

                if (is_string($from) && is_string($to)
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)
                    && $from <= $to) {
                    return [$from, $to];
                }
                break;
        }

        // fiscal_year (the default) — the selected year's start to today; falls
        // back to the current fiscal year, then the calendar year to date.
        $year = $fiscalYearId !== null ? $fiscalYears->firstWhere('id', $fiscalYearId) : null;
        $year = $year ?? $this->currentFiscalYear($fiscalYears, $today);

        if ($year !== null) {
            return [Carbon::parse($year->start_date)->toDateString(), $today->toDateString()];
        }

        return [$today->copy()->startOfYear()->toDateString(), $today->toDateString()];
    }

    private function currentFiscalYear(Collection $fiscalYears, Carbon $today): ?FiscalYear
    {
        return $fiscalYears->first(
            fn (FiscalYear $fy) => $fy->start_date->lte($today) && $fy->end_date->gte($today),
        );
    }
}
