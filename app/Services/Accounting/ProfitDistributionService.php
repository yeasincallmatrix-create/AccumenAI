<?php

namespace App\Services\Accounting;

use App\Models\Partner;
use App\Models\Shareholder;

class ProfitDistributionService
{
    /**
     * Compute partner-wise profit distribution.
     */
    public function computePartnerDistribution(int $instituteId, float $netProfit): array
    {
        return Partner::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'partner_id' => $p->id,
                'name' => $p->name,
                'share_percent' => (float) $p->share_percent,
                'profit_share' => round($netProfit * ($p->share_percent / 100), 2),
            ])->toArray();
    }

    /**
     * Compute shareholder dividend distribution.
     */
    public function computeDividendDistribution(int $instituteId, float $totalDividend): array
    {
        $totalShares = (int) Shareholder::where('institute_id', $instituteId)
            ->where('is_active', true)->sum('shares');

        return Shareholder::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($sh) => [
                'shareholder_id' => $sh->id,
                'name' => $sh->name,
                'shares' => $sh->shares,
                'share_percent' => (float) $sh->share_percent,
                'dividend_per_share' => $sh->shares > 0
                    ? round($totalDividend / max(1, $totalShares), 4)
                    : 0,
                'dividend_amount' => round($totalDividend * ($sh->share_percent / 100), 2),
            ])->toArray();
    }

    /**
     * Journal entry for partner distribution.
     *
     * PLACEHOLDER — Not implemented in G.2.
     * JournalPostingService requires fiscal-year + period resolution.
     * Wire when distribution UI/approval flow is added (future phase).
     */
    public function postPartnerDistribution(int $instituteId, array $distribution): void
    {
        // Intentionally empty.
        // Future: build entries + call JournalPostingService::post()
    }
}
