<?php

namespace App\Console\Commands;

use App\Models\Medical\Medicine;
use App\Services\Medical\DgdaService;
use Illuminate\Console\Command;

/**
 * Validate locally-coded medicines against the DGDA terminology server.
 *
 * Only rows that already carry a dgda_code are checked (real DAR codes must
 * come from the registry — this command never invents them). Unreachable
 * server? Rows stay pending/failed; run again later.
 *
 *   php artisan medical:dgda-sync --code=353-0026-039
 *   php artisan medical:dgda-sync --limit=200
 */
class SyncDgdaDrugs extends Command
{
    protected $signature = 'medical:dgda-sync
                            {--code= : Validate a single DGDA code}
                            {--limit=200 : Max coded rows to check per run}
                            {--institute= : Limit to one institute id}';

    protected $description = 'Validate DGDA drug codes against the terminology server.';

    public function handle(DgdaService $service): int
    {
        if (! DgdaService::enabled()) {
            $this->warn('DGDA is disabled (Configuration Center → Medical). Enable it first, then sync.');

            return self::SUCCESS;
        }

        $onlyInstitute = $this->option('institute') !== null && trim((string) $this->option('institute')) !== ''
            ? (int) $this->option('institute')
            : null;

        if ($onlyInstitute !== null && ! DgdaService::isEnabledForInstitute($onlyInstitute)) {
            $this->warn("DGDA is not enabled for institute {$onlyInstitute}. Sync skipped.");

            return self::SUCCESS;
        }

        $query = Medicine::whereNotNull('dgda_code')->where('dgda_code', '!=', '');

        if ($onlyInstitute !== null) {
            $query->where('institute_id', $onlyInstitute);
        } else {
            $enabledIds = \App\Models\InstituteSetting::withoutGlobalScopes()
                ->where('dgda_enabled', true)
                ->pluck('institute_id')
                ->all();
            if ($enabledIds === []) {
                $this->info('Nothing to sync: no institute has DGDA enabled.');

                return self::SUCCESS;
            }
            $query->whereIn('institute_id', $enabledIds);
        }

        if ($this->option('code') !== null && trim((string) $this->option('code')) !== '') {
            $query->where('dgda_code', trim((string) $this->option('code')));
        }

        $rows = $query->limit(max(1, min((int) $this->option('limit'), 1000)))->get();
        if ($rows->isEmpty()) {
            $this->info('Nothing to sync: no catalog rows carry a DGDA code yet.');

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;
        foreach ($rows as $medicine) {
            $result = $service->validateCode((string) $medicine->dgda_code);
            if (($result['ok'] ?? false) && ($result['valid'] ?? false)) {
                $service->markSynced($medicine, $result['display'] ?? null);
                $synced++;
            } else {
                $service->markFailed($medicine);
                $failed++;
                $this->warn("{$medicine->dgda_code}: ".($result['error'] ?? 'not confirmed by registry'));
            }
        }

        $this->info("DGDA sync done: {$synced} synced, {$failed} failed/pending.");

        return self::SUCCESS;
    }
}
