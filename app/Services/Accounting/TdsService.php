<?php

namespace App\Services\Accounting;

use App\Models\TdsCertificate;
use App\Models\TdsDeduction;
use App\Models\TaxDeductionRule;
use Illuminate\Support\Facades\DB;

class TdsService
{
    public function compute(string $type, float $amount, ?string $country = null): array
    {
        $country = $country ?? tenant_country();
        $rule = TaxDeductionRule::forCountry($country)
            ->active()
            ->where('code', $type)
            ->first();

        if (! $rule) {
            return [
                'rate' => 0,
                'tax_amount' => 0,
                'gross_amount' => $amount,
                'rule' => null,
            ];
        }

        $taxAmount = $rule->threshold && $amount < $rule->threshold
            ? 0
            : round($amount * $rule->rate / 100, 2);

        return [
            'rate' => (float) $rule->rate,
            'tax_amount' => $taxAmount,
            'gross_amount' => $amount,
            'rule' => $rule,
        ];
    }

    public function record(int $instituteId, array $data): TdsDeduction
    {
        return DB::transaction(function () use ($instituteId, $data) {
            $country = tenant_country($instituteId);
            $currency = tenant_currency($instituteId);
            $referenceNo = $this->generateReferenceNo($instituteId);

            $computeResult = $this->compute(
                $data['type'],
                (float) $data['gross_amount'],
                $country
            );

            return TdsDeduction::create([
                'institute_id' => $instituteId,
                'rule_id' => $computeResult['rule']?->id,
                'country_code' => $country,
                'currency_code' => $currency,
                'reference_no' => $referenceNo,
                'type' => $data['type'],
                'payee_name' => $data['payee_name'],
                'payee_tin' => $data['payee_tin'] ?? null,
                'gross_amount' => $data['gross_amount'],
                'tax_rate' => $computeResult['rate'],
                'tax_amount' => $computeResult['tax_amount'],
                'deduction_date' => $data['deduction_date'],
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function markDeposited(TdsDeduction $tds, array $data): TdsDeduction
    {
        $tds->update([
            'deposit_date' => $data['deposit_date'],
            'deposit_challan_no' => $data['challan_no'],
            'status' => 'deposited',
        ]);

        return $tds->fresh();
    }

    public function generateCertificate(int $instituteId, int $deductionId, array $data = []): TdsCertificate
    {
        return DB::transaction(function () use ($instituteId, $deductionId, $data) {
            $country = tenant_country($instituteId);
            $deduction = TdsDeduction::where('institute_id', $instituteId)->findOrFail($deductionId);

            $certificateNo = $this->generateCertificateNo($instituteId);

            $certificate = TdsCertificate::create([
                'institute_id' => $instituteId,
                'deduction_id' => $deductionId,
                'country_code' => $country,
                'certificate_no' => $certificateNo,
                'financial_year' => $data['financial_year'] ?? $this->currentFinancialYear($country),
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'status' => 'issued',
            ]);

            $deduction->update(['status' => 'certificate_issued']);

            return $certificate;
        });
    }

    public function getSummary(int $instituteId, string $period): array
    {
        $country = tenant_country($instituteId);

        $deductions = TdsDeduction::where('institute_id', $instituteId)
            ->whereYear('deduction_date', $period)
            ->get();

        return [
            'country_code' => $country,
            'period' => $period,
            'total_deductions' => $deductions->count(),
            'total_gross' => $deductions->sum('gross_amount'),
            'total_tax' => $deductions->sum('tax_amount'),
            'pending_deposit' => $deductions->where('status', 'pending')->count(),
            'deposited' => $deductions->where('status', 'deposited')->count(),
            'certificates_issued' => $deductions->where('status', 'certificate_issued')->count(),
            'by_type' => $deductions->groupBy('type')->map(fn ($items) => [
                'count' => $items->count(),
                'gross' => $items->sum('gross_amount'),
                'tax' => $items->sum('tax_amount'),
            ])->toArray(),
        ];
    }

    private function generateReferenceNo(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'TDS-' . $year . '-' . str_pad($instituteId, 4, '0', STR_PAD_LEFT) . '-';
        $last = TdsDeduction::where('institute_id', $instituteId)
            ->where('reference_no', 'like', $prefix . '%')
            ->count();

        return $prefix . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    private function generateCertificateNo(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'TDS-' . $year . '-' . str_pad($instituteId, 4, '0', STR_PAD_LEFT) . '-';
        $last = TdsCertificate::where('institute_id', $instituteId)
            ->where('certificate_no', 'like', $prefix . '%')
            ->count();

        return $prefix . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    private function currentFinancialYear(string $country): string
    {
        $now = now();
        return match ($country) {
            'BD' => $now->month >= 7
                ? $now->year . '-' . ($now->year + 1)
                : ($now->year - 1) . '-' . $now->year,
            'IN' => $now->month >= 4
                ? $now->year . '-' . substr((string) ($now->year + 1), -2)
                : substr((string) ($now->year - 1), -2) . '-' . substr((string) $now->year, -2),
            default => (string) $now->year,
        };
    }
}
