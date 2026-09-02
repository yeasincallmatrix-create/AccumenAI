<?php

namespace App\Jobs;

use App\Models\Institute;
use App\Services\Accounting\FxRevaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled FX revaluation for all eligible institutes.
 *
 * Iterates active institutes, revaluates all open foreign-currency positions
 * as of the previous month-end, and delegates to FxRevaluationService::run()
 * which enforces: idempotency (business key lookup), period-open validation,
 * missing-rate rejection, and audit logging.
 *
 * One tenant failure does not stop other tenants.
 */
class FxRevaluationJob implements ShouldQueue
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
        $lockKey = "fx_revaluation_{$instituteId}";

        $locked = Cache::lock($lockKey, 120);
        if (! $locked->get()) {
            Log::info("FxRevaluationJob: skipped institute {$instituteId} — lock held.");
            return;
        }

        try {
            $asOfDate = now()->subMonth()->endOfMonth()->toDateString();

            $service = app(FxRevaluationService::class);
            $result = $service->run($instituteId, null, ['as_of_date' => $asOfDate], null);

            Log::info("FxRevaluationJob: completed for institute {$instituteId} as of {$asOfDate}.", [
                'currencies_processed' => count($result['processed'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error("FxRevaluationJob: failed for institute {$instituteId}: {$e->getMessage()}", [
                'exception' => $e,
                'institute_id' => $instituteId,
            ]);
        } finally {
            $locked->release();
        }
    }
}
