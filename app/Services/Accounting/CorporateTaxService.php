<?php

namespace App\Services\Accounting;

use App\Models\AdvanceTaxPayment;
use App\Models\CorporateTaxComputation;
use Illuminate\Support\Facades\DB;

class CorporateTaxService
{
    public const RATE_MATRIX = [
        'BD' => [
            'private_limited' => ['rate' => 25.00, 'min_tax_rate' => 0.60],
            'public_limited' => ['rate' => 22.50, 'min_tax_rate' => 0.60],
            'bank' => ['rate' => 37.50, 'min_tax_rate' => 0.60],
            'insurance' => ['rate' => 37.50, 'min_tax_rate' => 0.60],
            'sole_proprietorship' => ['rate' => 25.00, 'min_tax_rate' => 0.60],
            'partnership' => ['rate' => 25.00, 'min_tax_rate' => 0.60],
        ],
        'IN' => [
            'private_limited' => ['rate' => 30.00, 'min_tax_rate' => 15.00],
            'public_limited' => ['rate' => 30.00, 'min_tax_rate' => 15.00],
            'bank' => ['rate' => 30.00, 'min_tax_rate' => 15.00],
            'insurance' => ['rate' => 30.00, 'min_tax_rate' => 15.00],
            'sole_proprietorship' => ['rate' => 30.00, 'min_tax_rate' => 15.00],
            'partnership' => ['rate' => 30.00, 'min_tax_rate' => 15.00],
        ],
        'US' => [
            'private_limited' => ['rate' => 21.00, 'min_tax_rate' => 0],
            'public_limited' => ['rate' => 21.00, 'min_tax_rate' => 0],
            'bank' => ['rate' => 21.00, 'min_tax_rate' => 0],
            'insurance' => ['rate' => 21.00, 'min_tax_rate' => 0],
            'sole_proprietorship' => ['rate' => 21.00, 'min_tax_rate' => 0],
            'partnership' => ['rate' => 21.00, 'min_tax_rate' => 0],
        ],
    ];

    public function rateFor(string $country, string $entityType): array
    {
        $rates = self::RATE_MATRIX[$country] ?? self::RATE_MATRIX['US'];

        return $rates[$entityType] ?? $rates['private_limited'];
    }

    public function compute(int $instituteId, string $fy, array $data): CorporateTaxComputation
    {
        return DB::transaction(function () use ($instituteId, $fy, $data) {
            $country = tenant_country($instituteId);
            $currency = tenant_currency($instituteId);

            $entityType = $data['entity_type'] ?? 'private_limited';
            $rateInfo = $this->rateFor($country, $entityType);

            $totalIncome = (float) ($data['total_income'] ?? 0);
            $deductions = (float) ($data['deductions'] ?? 0);
            $taxableIncome = max(0, $totalIncome - $deductions);

            $taxAmount = round($taxableIncome * $rateInfo['rate'] / 100, 2);
            $minimumTax = round($totalIncome * $rateInfo['min_tax_rate'] / 100, 2);
            $finalTax = max($taxAmount, $minimumTax);
            $advancePaid = (float) ($data['advance_tax_paid'] ?? 0);
            $taxPayable = max(0, $finalTax - $advancePaid);

            $referenceNo = $this->generateReferenceNo($instituteId);

            return CorporateTaxComputation::create([
                'institute_id' => $instituteId,
                'country_code' => $country,
                'currency_code' => $currency,
                'reference_no' => $referenceNo,
                'financial_year' => $fy,
                'entity_type' => $entityType,
                'total_income' => $totalIncome,
                'deductions' => $deductions,
                'taxable_income' => $taxableIncome,
                'tax_rate' => $rateInfo['rate'],
                'tax_amount' => $taxAmount,
                'minimum_tax' => $minimumTax,
                'final_tax' => $finalTax,
                'advance_tax_paid' => $advancePaid,
                'tax_payable' => $taxPayable,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function recordAdvancePayment(int $instituteId, array $data): AdvanceTaxPayment
    {
        return DB::transaction(function () use ($instituteId, $data) {
            $country = tenant_country($instituteId);
            $currency = tenant_currency($instituteId);

            $referenceNo = $this->generateAdvanceReferenceNo($instituteId);
            $rateInfo = $this->rateFor($country, $data['entity_type'] ?? 'private_limited');
            $estimatedIncome = (float) ($data['estimated_income'] ?? 0);
            $taxAmount = round($estimatedIncome * $rateInfo['rate'] / 100, 2);

            return AdvanceTaxPayment::create([
                'institute_id' => $instituteId,
                'country_code' => $country,
                'currency_code' => $currency,
                'reference_no' => $referenceNo,
                'financial_year' => $data['financial_year'] ?? $this->currentFinancialYear($country),
                'quarter' => $data['quarter'] ?? 'Q1',
                'estimated_income' => $estimatedIncome,
                'tax_rate' => $rateInfo['rate'],
                'tax_amount' => $taxAmount,
                'due_date' => $data['due_date'],
                'status' => 'due',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function getAdvanceTaxSummary(int $instituteId, string $fy): array
    {
        $payments = AdvanceTaxPayment::where('institute_id', $instituteId)
            ->where('financial_year', $fy)
            ->get();

        return [
            'financial_year' => $fy,
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('tax_amount'),
            'paid' => $payments->where('status', 'paid')->sum('tax_amount'),
            'due' => $payments->where('status', 'due')->sum('tax_amount'),
            'overdue' => $payments->where('status', 'overdue')->sum('tax_amount'),
            'by_quarter' => $payments->groupBy('quarter')->map(fn ($items) => [
                'count' => $items->count(),
                'amount' => $items->sum('tax_amount'),
                'paid' => $items->where('status', 'paid')->sum('tax_amount'),
            ])->toArray(),
        ];
    }

    public function getQuarterDueDates(string $fy, ?string $country = null): array
    {
        $country = $country ?? tenant_country();

        return match ($country) {
            'BD' => [
                'Q1' => $fy[0] . '-' . substr($fy, 2, 2) . '-09-15',
                'Q2' => $fy[0] . '-' . substr($fy, 2, 2) . '-12-15',
                'Q3' => substr($fy, 5, 4) . '-03-15',
                'Q4' => substr($fy, 5, 4) . '-06-15',
            ],
            'IN' => [
                'Q1' => $fy[0] . '-' . substr($fy, 2, 2) . '-06-15',
                'Q2' => $fy[0] . '-' . substr($fy, 2, 2) . '-09-15',
                'Q3' => $fy[0] . '-' . substr($fy, 2, 2) . '-12-15',
                'Q4' => substr($fy, 5, 4) . '-03-15',
            ],
            default => [
                'Q1' => $fy . '-04-15',
                'Q2' => $fy . '-06-15',
                'Q3' => $fy . '-09-15',
                'Q4' => $fy . '-12-15',
            ],
        };
    }

    private function generateReferenceNo(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'CT-' . $year . '-' . str_pad($instituteId, 4, '0', STR_PAD_LEFT) . '-';
        $last = CorporateTaxComputation::where('institute_id', $instituteId)
            ->where('reference_no', 'like', $prefix . '%')
            ->count();

        return $prefix . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    private function generateAdvanceReferenceNo(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'AT-' . $year . '-' . str_pad($instituteId, 4, '0', STR_PAD_LEFT) . '-';
        $last = AdvanceTaxPayment::where('institute_id', $instituteId)
            ->where('reference_no', 'like', $prefix . '%')
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
