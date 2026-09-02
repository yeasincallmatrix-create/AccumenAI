<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\TaxReturnPeriod;
use App\Models\TaxReturnLine;
use Illuminate\Support\Facades\DB;

/**
 * STEP 82 — Tax Reporting Engine.
 *
 * VAT summary, input VAT, output VAT, tax liability, and tax transaction
 * detail. All figures derived from posted journal entries linked to tax
 * CoA accounts (1201 = Input VAT, 2100 = VAT Payable).
 */
class TaxReportService
{
    /**
     * VAT summary: collected (output) vs paid (input) and net liability
     * for a date range.
     *
     * @return array{output_vat: float, input_vat: float, net_liability: float, transactions: int}
     */
    public function vatSummary(int $instituteId, ?int $branchId, string $from, string $to): array
    {
        $outputVat = $this->accountBalance($instituteId, $branchId, '2100', $from, $to);
        $inputVat = $this->accountBalance($instituteId, $branchId, '1201', $from, $to);

        $transactions = $this->taxTransactionCount($instituteId, $branchId, $from, $to);

        return [
            'output_vat' => round($outputVat, 4),
            'input_vat' => round($inputVat, 4),
            'net_liability' => round($outputVat - $inputVat, 4),
            'transactions' => $transactions,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Input VAT detail: debit postings to the Input VAT account.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function inputVatDetail(int $instituteId, ?int $branchId, string $from, string $to): \Illuminate\Support\Collection
    {
        $account = ChartOfAccount::where('institute_id', $instituteId)
            ->where('code', '1201')
            ->first();

        if (!$account) {
            return collect();
        }

        return DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('je.branch_id', $branchId))
            ->where('je.coa_id', $account->id)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereDate('je.journal_date', '>=', $from)
            ->whereDate('je.journal_date', '<=', $to)
            ->select('je.*', 'j.journal_no', 'j.description as journal_description')
            ->orderBy('je.journal_date')
            ->get();
    }

    /**
     * Output VAT detail: credit postings to the VAT Payable account.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function outputVatDetail(int $instituteId, ?int $branchId, string $from, string $to): \Illuminate\Support\Collection
    {
        $account = ChartOfAccount::where('institute_id', $instituteId)
            ->where('code', '2100')
            ->first();

        if (!$account) {
            return collect();
        }

        return DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('je.branch_id', $branchId))
            ->where('je.coa_id', $account->id)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereDate('je.journal_date', '>=', $from)
            ->whereDate('je.journal_date', '<=', $to)
            ->select('je.*', 'j.journal_no', 'j.description as journal_description')
            ->orderBy('je.journal_date')
            ->get();
    }

    /**
     * Tax liability report: net payable balance as of a date.
     *
     * @return array{vat_payable: float, wht_payable: float, tax_clearing: float, total_liability: float}
     */
    public function taxLiability(int $instituteId, ?int $branchId, ?string $asOfDate = null): array
    {
        $asOf = $asOfDate ?? now()->toDateString();

        $vatPayable = $this->accountBalance($instituteId, $branchId, '2100', null, $asOf);
        $whtPayable = $this->accountBalance($instituteId, $branchId, '2101', null, $asOf);
        $taxClearing = $this->accountBalance($instituteId, $branchId, '2102', null, $asOf);

        return [
            'vat_payable' => round($vatPayable, 4),
            'wht_payable' => round($whtPayable, 4),
            'tax_clearing' => round($taxClearing, 4),
            'total_liability' => round($vatPayable + $whtPayable + $taxClearing, 4),
            'as_of_date' => $asOf,
        ];
    }

    /**
     * Tax transaction detail: all journal entries touching tax accounts.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function taxTransactionDetail(int $instituteId, ?int $branchId, string $from, string $to): \Illuminate\Support\Collection
    {
        $taxCodes = ['1201', '2100', '2101', '2102'];
        $taxAccountIds = ChartOfAccount::where('institute_id', $instituteId)
            ->whereIn('code', $taxCodes)
            ->pluck('id');

        if ($taxAccountIds->isEmpty()) {
            return collect();
        }

        return DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->where('je.institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('je.branch_id', $branchId))
            ->whereIn('je.coa_id', $taxAccountIds)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereDate('je.journal_date', '>=', $from)
            ->whereDate('je.journal_date', '<=', $to)
            ->select('je.*', 'j.journal_no', 'j.description as journal_description', 'coa.code as account_code', 'coa.name as account_name')
            ->orderBy('je.journal_date')
            ->get();
    }

    /**
     * Sum of posted journal entries for a given CoA code in a date range.
     */
    private function accountBalance(int $instituteId, ?int $branchId, string $code, ?string $from, ?string $to): float
    {
        $account = ChartOfAccount::where('institute_id', $instituteId)
            ->where('code', $code)
            ->first();

        if (!$account) {
            return 0.0;
        }

        return (float) DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('je.branch_id', $branchId))
            ->where('je.coa_id', $account->id)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->when($from !== null, fn ($q) => $q->whereDate('je.journal_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('je.journal_date', '<=', $to))
            ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) AS balance')
            ->value('balance');
    }

    /**
     * Count of distinct tax account journal entries in a date range.
     */
    private function taxTransactionCount(int $instituteId, ?int $branchId, string $from, string $to): int
    {
        $taxCodes = ['1201', '2100', '2101', '2102'];
        $taxAccountIds = ChartOfAccount::where('institute_id', $instituteId)
            ->whereIn('code', $taxCodes)
            ->pluck('id');

        if ($taxAccountIds->isEmpty()) {
            return 0;
        }

        return (int) DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('je.branch_id', $branchId))
            ->whereIn('je.coa_id', $taxAccountIds)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereDate('je.journal_date', '>=', $from)
            ->whereDate('je.journal_date', '<=', $to)
            ->count('je.id');
    }
}
