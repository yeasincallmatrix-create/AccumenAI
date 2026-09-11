<?php

namespace App\Console\Commands;

use App\Models\Medical\RxNormImportBatch;
use App\Services\Medical\RxNormImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 12 — operator-controlled RxNorm terminology import (NLM RxNav).
 *
 * Consumes an operator-provided export shaped like RxNav concept records —
 * never live-scraped, never fabricated RXCUIs:
 *
 *   php artisan medical:sync-rxnorm storage/app/rxnorm/rxconcepts.csv --dry-run
 *   php artisan medical:sync-rxnorm storage/app/rxnorm/rxconcepts.csv --release="2026-09" --limit=500
 *
 * Writes only global terminology (concepts/ingredients/products/
 * identifiers); never tenant catalogs, prices, stock, prescriptions or
 * DGDA rows. Every run is a recorded batch; absence from one import never
 * retires anything.
 */
class SyncRxNorm extends Command
{
    protected $signature = 'medical:sync-rxnorm
                            {file : Path to the RxNorm concept export CSV}
                            {--dry-run : resolve without persisting}
                            {--limit=0 : max rows (0 = all)}
                            {--release= : source release label (e.g. 2026-09)}';

    protected $description = 'Import RxNorm concepts into terminology mappings (Phase 12)';

    public function handle(RxNormImportService $service): int
    {
        $path = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $release = $this->option('release') !== null && trim((string) $this->option('release')) !== ''
            ? trim((string) $this->option('release'))
            : null;

        try {
            $parsed = $service->parseFile($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = $parsed['rows'];
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        $batch = RxNormImportBatch::create([
            'source' => 'rxnorm_rxnav_export',
            'release_version' => $release,
            'filename' => basename($path),
            'checksum' => hash_file('sha256', $path) ?: null,
            'status' => RxNormImportBatch::STATUS_PENDING,
            'started_at' => now(),
        ]);

        $counters = [
            'seen' => 0, 'valid' => 0, 'created' => 0, 'updated' => 0,
            'unchanged' => 0, 'unmapped' => 0, 'ambiguous' => 0, 'rejected' => 0,
        ];
        $errors = [];
        $fatal = null;

        $work = function () use ($service, $rows, $batch, $release, &$counters, &$errors) {
            foreach ($rows as $row) {
                $counters['seen']++;
                try {
                    $check = $service->validateRow($row['data'], $row['line'], $release);
                } catch (\Throwable $e) {
                    $errors[] = "line {$row['line']}: validation crashed: {$e->getMessage()}";
                    $counters['rejected']++;
                    continue;
                }
                foreach ($check['errors'] as $error) {
                    $errors[] = $error;
                }
                if (! $check['valid']) {
                    $counters['rejected']++;
                    continue;
                }
                $counters['valid']++;
                try {
                    $outcome = $service->importRow($check['record'], $batch, $counters);
                } catch (\Throwable $e) {
                    $errors[] = "line {$row['line']}: {$e->getMessage()}";
                    $counters['rejected']++;
                    continue;
                }
                if ($outcome === 'unmapped') {
                    $counters['unmapped']++;
                } elseif ($outcome === 'ambiguous') {
                    $counters['ambiguous']++;
                }
            }
        };

        try {
            if ($dryRun) {
                DB::transaction(function () use ($work) {
                    $work();
                    throw new \RuntimeException('dry-run rollback');
                });
            } else {
                $work();
            }
        } catch (\RuntimeException $e) {
            if (! $dryRun || $e->getMessage() !== 'dry-run rollback') {
                $fatal = $e->getMessage();
            }
        } catch (\Throwable $e) {
            $fatal = get_class($e).': '.$e->getMessage();
        }

        if (! $dryRun) {
            $batch->update([
                'status' => $fatal !== null
                    ? ($counters['seen'] > 0 ? RxNormImportBatch::STATUS_PARTIAL : RxNormImportBatch::STATUS_FAILED)
                    : RxNormImportBatch::STATUS_COMPLETE,
                'records_seen' => $counters['seen'],
                'records_valid' => $counters['valid'],
                'records_created' => $counters['created'],
                'records_updated' => $counters['updated'],
                'records_unchanged' => $counters['unchanged'],
                'records_rejected' => $counters['rejected'],
                'records_unmapped' => $counters['unmapped'],
                'records_ambiguous' => $counters['ambiguous'],
                'error_summary' => $fatal ?? (implode("\n", array_slice($errors, 0, 20)) ?: null),
                'completed_at' => now(),
            ]);
        } else {
            $batch->delete();
        }

        $this->table(['metric', 'count'], [
            ['records seen', $counters['seen']],
            ['valid', $counters['valid']],
            ['created', $counters['created']],
            ['updated', $counters['updated']],
            ['unchanged', $counters['unchanged']],
            ['rejected', $counters['rejected']],
            ['unmapped', $counters['unmapped']],
            ['ambiguous', $counters['ambiguous']],
        ]);
        if ($fatal !== null) {
            $this->error('FAILED: '.$fatal);
        } elseif ($dryRun) {
            $this->info('[dry-run] no rows persisted.');
        } else {
            $this->info("Batch #{$batch->id} {$batch->fresh()->status}.");
        }

        return $fatal !== null ? self::FAILURE : self::SUCCESS;
    }
}
