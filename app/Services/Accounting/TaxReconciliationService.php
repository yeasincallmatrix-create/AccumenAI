<?php

namespace App\Services\Accounting;

use App\Models\AdvanceTaxPayment;
use App\Models\CorporateTaxComputation;
use App\Models\TaxReturnReconciliation;
use App\Models\TdsDeduction;
use App\Models\TdsReceivable;
use Illuminate\Support\Facades\DB;

class TaxReconciliationService
{
    public function compute(int $instituteId, string $fy): array
    {
        $tdsPayable = (float) TdsDeduction::where('institute_id', $instituteId)
            ->where('deduction_date', '>=', $this->fyStart($fy))
            ->where('deduction_date', '<=', $this->fyEnd($fy))
            ->sum('tax_amount');

        $tdsReceivable = (float) TdsReceivable::where('institute_id', $instituteId)
            ->where('financial_year', $fy)
            ->sum('tds_amount');

        $advance = (float) AdvanceTaxPayment::where('institute_id', $instituteId)
            ->where('financial_year', $fy)
            ->where('status', 'paid')
            ->sum('tax_amount');

        $corp = CorporateTaxComputation::where('institute_id', $instituteId)
            ->where('financial_year', $fy)
            ->first();

        $corpTax = (float) ($corp->tax_payable ?? 0);

        $totalLiability = $corpTax;
        $totalCredits = $tdsReceivable + $advance;
        $net = round($totalLiability - $totalCredits, 2);

        return [
            'financial_year' => $fy,
            'tds_payable_total' => $tdsPayable,
            'tds_receivable_total' => $tdsReceivable,
            'advance_tax_paid' => $advance,
            'corporate_tax_payable' => $corpTax,
            'total_tax_liability' => $totalLiability,
            'total_credits' => $totalCredits,
            'net_payable' => $net,
            'currency_code' => tenant_currency($instituteId),
            'country_code' => tenant_country($instituteId),
        ];
    }

    public function finalize(int $instituteId, string $fy, array $extra = []): TaxReturnReconciliation
    {
        $data = $this->compute($instituteId, $fy);

        return TaxReturnReconciliation::updateOrCreate(
            ['institute_id' => $instituteId, 'financial_year' => $fy],
            array_merge($data, [
                'status' => 'computed',
                'breakdown' => $this->breakdown($instituteId, $fy),
                'notes' => $extra['notes'] ?? null,
                'created_by' => auth()->id(),
            ])
        );
    }

    public function markFiled(TaxReturnReconciliation $r, array $data): TaxReturnReconciliation
    {
        $r->update([
            'status' => 'filed',
            'filing_date' => $data['filing_date'] ?? now()->toDateString(),
            'acknowledgment_no' => $data['acknowledgment_no'] ?? null,
        ]);

        return $r->fresh();
    }

    protected function breakdown(int $instituteId, string $fy): array
    {
        return [
            'by_deductor' => TdsReceivable::where('institute_id', $instituteId)
                ->where('financial_year', $fy)
                ->select('party_id', DB::raw('SUM(tds_amount) as total'))
                ->groupBy('party_id')
                ->get()
                ->toArray(),
        ];
    }

    protected function fyStart(string $fy): string
    {
        $parts = explode('-', $fy);
        return $parts[0] . '-07-01';
    }

    protected function fyEnd(string $fy): string
    {
        $parts = explode('-', $fy);
        return $parts[1] . '-06-30';
    }
}
