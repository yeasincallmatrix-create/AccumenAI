<?php

namespace App\Services\FixedAsset;

use App\Models\FixedAsset;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Fixed-asset reporting foundations (STEP 17): register, by category/branch,
 * depreciation schedule and net-book-value aggregation.
 */
class FixedAssetReportService
{
    public function __construct(
        private readonly DepreciationEngine $engine,
    ) {}

    /**
     * Asset register with derived NBV.
     */
    public function register(int $instituteId, ?int $branchId, ?string $status = null, int $perPage = 50): LengthAwarePaginator
    {
        return FixedAsset::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->with(['category', 'location'])
            ->orderBy('asset_code')
            ->paginate($perPage);
    }

    /**
     * @return array<int, array{period:int, opening_nbv:float, depreciation:float, accumulated_depreciation:float, closing_nbv:float}>
     */
    public function depreciationSchedule(FixedAsset $asset, ?array $unitsPerPeriod = null): array
    {
        return $this->engine->schedule($asset, $unitsPerPeriod);
    }

    /**
     * @return array<string, array{count:int, cost:float, accumulated_depreciation:float, nbv:float}>
     */
    public function byCategory(int $instituteId, ?int $branchId): array
    {
        return FixedAsset::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->get()
            ->groupBy(fn ($a) => $a->category?->name ?? 'Uncategorized')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'cost' => round($group->sum(fn ($a) => $a->cost()), 4),
                'accumulated_depreciation' => round($group->sum(fn ($a) => (float) $a->accumulated_depreciation), 4),
                'nbv' => round($group->sum(fn ($a) => $a->netBookValue()), 4),
            ])
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<string, array{count:int, cost:float, nbv:float}>
     */
    public function byBranch(int $instituteId): array
    {
        return FixedAsset::query()
            ->where('institute_id', $instituteId)
            ->get()
            ->groupBy(fn ($a) => $a->branch?->name ?? 'Institute-wide')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'cost' => round($group->sum(fn ($a) => $a->cost()), 4),
                'nbv' => round($group->sum(fn ($a) => $a->netBookValue()), 4),
            ])
            ->sortKeys()
            ->all();
    }
}
