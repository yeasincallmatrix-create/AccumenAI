<?php

namespace App\Jobs;

use App\Models\AssetDepreciationRun;
use App\Models\FixedAsset;
use App\Models\Institute;
use App\Services\FixedAsset\FixedAssetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled depreciation run for all eligible institutes.
 *
 * Iterates active institutes, resolves the previous calendar month as the
 * depreciation period, and delegates to FixedAssetService::runDepreciation()
 * which enforces: idempotency (unique constraint on institute+branch+period),
 * period-open validation, fully-depreciated exclusion, and audit logging.
 *
 * One tenant failure does not stop other tenants.
 */
class DepreciationRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function handle(): void
    {
        $institutes = Institute::query()->where('status', 'active')->get();

        foreach ($institutes as $institute) {
            $this->processInstitute($institute->id);
        }
    }

    private function processInstitute(int $instituteId): void
    {
        $lockKey = "depreciation_run_{$instituteId}";

        $locked = Cache::lock($lockKey, 120);
        if (! $locked->get()) {
            Log::info("DepreciationRunJob: skipped institute {$instituteId} — lock held by another process.");
            return;
        }

        try {
            $periodStart = now()->subMonth()->startOfMonth()->toDateString();
            $periodEnd = now()->subMonth()->endOfMonth()->toDateString();

            $existingRun = AssetDepreciationRun::query()
                ->where('institute_id', $instituteId)
                ->where('period_start', $periodStart)
                ->exists();

            if ($existingRun) {
                Log::info("DepreciationRunJob: institute {$instituteId} already has run for {$periodStart}.");
                return;
            }

            $hasEligible = FixedAsset::query()
                ->where('institute_id', $instituteId)
                ->where('is_depreciable', true)
                ->whereIn('status', ['active', 'capitalized', 'under_maintenance'])
                ->whereNotNull('useful_life_months')
                ->exists();

            if (! $hasEligible) {
                Log::info("DepreciationRunJob: institute {$instituteId} has no depreciable assets.");
                return;
            }

            $service = app(FixedAssetService::class);
            $service->runDepreciation($instituteId, null, $periodStart, $periodEnd, null);

            Log::info("DepreciationRunJob: completed for institute {$instituteId}, period {$periodStart} to {$periodEnd}.");
        } catch (\Throwable $e) {
            Log::error("DepreciationRunJob: failed for institute {$instituteId}: {$e->getMessage()}", [
                'exception' => $e,
                'institute_id' => $instituteId,
            ]);
        } finally {
            $locked->release();
        }
    }
}
