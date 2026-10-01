<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\RestoreLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProgressService
{
    /**
     * Unified throttled progress update (avoid DB spam).
     * Writes only if stage changed OR percent moved >= 2.
     *
     * @param Backup|RestoreLog $model
     */
    public function update(Model $model, int $percent, string $stage, ?string $message = null): void
    {
        $type = $model instanceof Backup ? 'backup' : 'restore';
        $cacheKey = "{$type}-progress-throttle:{$model->id}";
        $last = Cache::get($cacheKey, ['percent' => -1, 'stage' => null]);

        if ($last['stage'] === $stage && abs($percent - $last['percent']) < 2) {
            return;
        }

        $model->update([
            'progress_percent' => min(100, max(0, $percent)),
            'progress_stage'   => $stage,
            'progress_message' => $message,
        ]);

        Cache::put($cacheKey, ['percent' => $percent, 'stage' => $stage], 300);

        Log::info("{$type} progress", [
            "{$type}_id" => $model->id,
            'percent'    => $percent,
            'stage'      => $stage,
        ]);
    }

    public function updateBackup(Backup $backup, int $percent, string $stage, ?string $message = null): void
    {
        $this->update($backup, $percent, $stage, $message);
    }

    public function updateRestore(RestoreLog $log, int $percent, string $stage, ?string $message = null): void
    {
        $this->update($log, $percent, $stage, $message);
    }

    public function clear(Backup|RestoreLog $model): void
    {
        $type = $model instanceof Backup ? 'backup' : 'restore';
        Cache::forget("{$type}-progress-throttle:{$model->id}");
    }
}
