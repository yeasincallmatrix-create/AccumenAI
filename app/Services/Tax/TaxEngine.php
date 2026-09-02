<?php

namespace App\Services\Tax;

use App\Models\TaxJurisdiction;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Support\TenantContext;

class TaxEngine
{
    public function resolveRates(int $instituteId, ?int $branchId, ?int $jurisdictionId = null, array $context = []): \Illuminate\Support\Collection
    {
        $itemType = $context['item_type'] ?? '*';
        $productCategory = $context['product_category'] ?? '*';
        $taxGroupId = $context['tax_group_id'] ?? null;
        $date = $context['date'] ?? now()->toDateString();

        $query = TaxRate::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));

        if ($branchId !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }

        if ($jurisdictionId !== null) {
            $query->where(function ($q) use ($jurisdictionId) {
                $q->where('jurisdiction_id', $jurisdictionId)
                    ->orWhereNull('jurisdiction_id');
            });
        } else {
            $query->whereNull('jurisdiction_id');
        }

        if ($taxGroupId !== null) {
            $query->where(function ($q) use ($taxGroupId) {
                $q->where('tax_group_id', $taxGroupId)->orWhereNull('tax_group_id');
            });
        }

        $rates = $query->get();

        $rules = TaxRule::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->where(function ($q) use ($itemType) {
                $q->where('item_type', $itemType)->orWhere('item_type', '*');
            })
            ->where(function ($q) use ($productCategory) {
                $q->where('product_category', $productCategory)->orWhere('product_category', '*');
            });

        if ($branchId !== null) {
            $rules->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }

        if ($jurisdictionId !== null) {
            $rules->where(function ($q) use ($jurisdictionId) {
                $q->where('jurisdiction_id', $jurisdictionId)->orWhereNull('jurisdiction_id');
            });
        }

        $anyRulesExist = \App\Models\TaxRule::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->exists();

        $ruleRateIds = $rules->pluck('tax_rate_id')->unique()->all();

        if ($anyRulesExist) {
            if ($ruleRateIds === []) {
                return collect();
            }

            return $rates->filter(fn ($rate) => in_array($rate->id, $ruleRateIds, false))
                ->sortBy('type')
                ->values();
        }

        return $rates->sortBy('type')->values();
    }

    public function jurisdictionForCountry(int $instituteId, ?int $branchId, string $countryIso): ?TaxJurisdiction
    {
        return TaxJurisdiction::query()
            ->where('institute_id', $instituteId)
            ->where('country_iso2', $countryIso)
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->first();
    }
}
