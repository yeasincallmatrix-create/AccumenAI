<?php

namespace App\Services\Tax;

use App\Models\TaxRate;
use Illuminate\Support\Collection;

class TaxCalculationService
{
    public function __construct(
        private readonly TaxEngine $engine,
    ) {}

    public function calculateLineTax(float $amount, TaxRate $rate): array
    {
        if ($rate->rate_type === 'fixed') {
            $taxAmount = round($rate->rate, 4);

            return [
                'tax_amount' => $taxAmount,
                'net_amount' => $rate->is_inclusive ? round($amount - $taxAmount, 4) : $amount,
                'gross_amount' => $rate->is_inclusive ? $amount : round($amount + $taxAmount, 4),
                'rate_id' => $rate->id,
                'rate' => $rate->rate,
                'type' => $rate->type,
                'is_inclusive' => $rate->is_inclusive,
            ];
        }

        if ($rate->is_inclusive) {
            $divisor = 1 + ($rate->rate / 100);
            $netAmount = round($amount / $divisor, 4);
            $taxAmount = round($amount - $netAmount, 4);

            return [
                'tax_amount' => $taxAmount,
                'net_amount' => $netAmount,
                'gross_amount' => $amount,
                'rate_id' => $rate->id,
                'rate' => $rate->rate,
                'type' => $rate->type,
                'is_inclusive' => true,
            ];
        }

        $taxAmount = round($amount * ($rate->rate / 100), 4);

        return [
            'tax_amount' => $taxAmount,
            'net_amount' => $amount,
            'gross_amount' => round($amount + $taxAmount, 4),
            'rate_id' => $rate->id,
            'rate' => $rate->rate,
            'type' => $rate->type,
            'is_inclusive' => false,
        ];
    }

    public function calculateItemTax(float $amount, Collection $rates, array $context = []): array
    {
        $results = [];
        $runningAmount = $amount;
        $totalTax = 0.0;

        $sorted = $rates->sortBy(fn ($r) => array_search($r->type, config('tax.compound_order', ['vat', 'excise', 'withholding'])))->values();

        foreach ($sorted as $rate) {
            $taxResult = $this->calculateLineTax($runningAmount, $rate);
            $results[] = $taxResult;
            $totalTax += $taxResult['tax_amount'];

            if ($rate->is_compound && ! $rate->is_inclusive) {
                $runningAmount += $taxResult['tax_amount'];
            }
        }

        return [
            'items' => $results,
            'total_tax' => round($totalTax, 4),
            'net_amount' => $amount,
            'gross_amount' => round($amount + $totalTax, 4),
        ];
    }

    public function calculateItemsTax(int $instituteId, ?int $branchId, array $items, ?int $jurisdictionId = null): array
    {
        $lineResults = [];
        $totalTax = 0.0;
        $totalNet = 0.0;
        $totalGross = 0.0;

        foreach ($items as $index => $item) {
            $amount = (float) $item['amount'];
            $context = [
                'item_type' => $item['item_type'] ?? '*',
                'product_category' => $item['product_category'] ?? '*',
                'tax_group_id' => $item['tax_group_id'] ?? null,
                'date' => $item['date'] ?? now()->toDateString(),
            ];

            $rates = $this->engine->resolveRates($instituteId, $branchId, $jurisdictionId, $context);

            $result = $this->calculateItemTax($amount, $rates, $context);

            $lineResults[$index] = $result;
            $totalTax += $result['total_tax'];
            $totalNet += $result['net_amount'];
            $totalGross += $result['gross_amount'];
        }

        return [
            'lines' => $lineResults,
            'total_tax' => round($totalTax, 4),
            'total_net' => round($totalNet, 4),
            'total_gross' => round($totalGross, 4),
        ];
    }
}
