<?php

namespace App\Services\FixedAsset;

use App\Models\AssetCategory;
use App\Models\AssetDepreciationEntry;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\JournalEntry;

/**
 * Fixed-asset subledger <-> GL reconciliation (STEP 17).
 *
 * Verifies that the cached fixed_assets.accumulated_depreciation equals the sum
 * of posted depreciation entries, and that the accumulated-depreciation control
 * account in the GL equals the subledger. Reports variance — never force-fixes.
 */
class FixedAssetReconciliationService
{
    public function reconcile(int $instituteId, ?int $branchId): array
    {
        $assets = FixedAsset::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->get();

        $ledger = AssetDepreciationEntry::query()
            ->where('institute_id', $instituteId)
            ->whereIn('asset_id', $assets->pluck('id'))
            ->get()
            ->groupBy('asset_id')
            ->map(fn ($group) => round($group->sum(fn ($e) => (float) $e->depreciation_amount), 4));

        $drifts = [];
        $subledgerTotal = 0.0;

        foreach ($assets as $asset) {
            $cached = round((float) $asset->accumulated_depreciation, 4);
            $sum = round((float) ($ledger[$asset->id] ?? 0), 4);
            $subledgerTotal += $sum;

            if (abs($cached - $sum) > 0.00005) {
                $drifts[] = [
                    'asset_id' => $asset->id,
                    'asset_code' => $asset->asset_code,
                    'cached' => $cached,
                    'ledger' => $sum,
                    'variance' => round($cached - $sum, 4),
                ];
            }
        }

        $glTotal = $this->accumulatedDepreciationGl($instituteId, $branchId);

        return [
            'subledger_total' => round($subledgerTotal, 4),
            'gl_total' => round($glTotal, 4),
            'variance' => round($glTotal - $subledgerTotal, 4),
            'asset_drifts' => $drifts,
        ];
    }

    /**
     * Net credit balance on the accumulated-depreciation control accounts.
     * Accumulated depreciation is a contra-asset (credit-normal), so the GL
     * balance is total credits minus debits.
     */
    private function accumulatedDepreciationGl(int $instituteId, ?int $branchId): float
    {
        $overrideIds = AssetCategory::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereNotNull('accumulated_depreciation_account_id')
            ->pluck('accumulated_depreciation_account_id')
            ->all();

        $coaIds = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->where(function ($q) use ($overrideIds) {
                $q->where('code', '1301')->orWhereIn('id', $overrideIds);
            })
            ->pluck('id');

        $rows = JournalEntry::query()
            ->where('institute_id', $instituteId)
            ->whereIn('coa_id', $coaIds)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->get();

        return round($rows->sum(fn ($e) => (float) $e->credit - (float) $e->debit), 4);
    }
}
