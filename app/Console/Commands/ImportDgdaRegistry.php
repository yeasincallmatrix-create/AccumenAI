<?php

namespace App\Console\Commands;

use App\Models\Medical\DgdaImportBatch;
use App\Services\Medical\DgdaImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11 — operator-controlled DGDA regulatory import.
 *
 * Consumes an officially obtained DGDA export (CSV matching the published
 * "Registered Drugs" columns) — never scraping, never fabricated data:
 *
 *   php artisan medical:dgda-import storage/app/dgda-exports/registered.csv --dry-run
 *   php artisan medical:dgda-import storage/app/dgda-exports/registered.csv --limit=500
 *
 * Never touches tenant catalogs, prices, stock, dispenses, prescriptions or
 * invoices. Every run is recorded as a batch (pending/partial/complete/
 * failed) with per-category counters; absence from one import never
 * deprecates records (partial/outage safety).
 */
class ImportDgdaRegistry extends Command
{
    protected $signature = 'medical:dgda-import
                            {file : Path to the official DGDA export CSV}
                            {--dry-run : resolve without persisting}
                            {--limit=0 : max rows (0 = all)}
                            {--source-version= : source version label}';

    protected $description = 'Import official DGDA registry export into regulatory records (Phase 11)';

    public function handle(DgdaImportService $service): int
    {
        $path = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        try {
            $parsed = $service->parseFile($path);
        } catch (\Throwable $e) {
            // Input problems fail cleanly with an exit code — never an
            // uncaught exception (which also breaks in-test PendingCommands).
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = $parsed['rows'];
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        $batch = DgdaImportBatch::create([
            'source' => 'dgda_official_export',
            'filename' => basename($path),
            'checksum' => hash_file('sha256', $path) ?: null,
            'status' => DgdaImportBatch::STATUS_PENDING,
            'source_version' => $this->option('source-version'),
            'started_at' => now(),
        ]);

        $counters = [
            'seen' => 0, 'valid' => 0, 'created' => 0, 'updated' => 0,
            'unchanged' => 0, 'matched' => 0, 'rejected' => 0,
            'unmatched' => 0, 'ambiguous' => 0,
        ];
        $errors = [];
        $fatal = null;

        $work = function () use ($service, $rows, $batch, &$counters, &$errors) {
            foreach ($rows as $row) {
                $counters['seen']++;
                try {
                    $check = $service->validateRow($row['data'], $row['line']);
                } catch (\Throwable $e) {
                    $errors[] = "line {$row['line']}: validation crashed: {$e->getMessage()}";
                    $counters['rejected']++;
                    continue;
                }
                foreach ($check['errors'] as $error) {
                    $errors[] = $error;
                }
                if ($check['record']['dar_number'] === '') {
                    $counters['rejected']++;
                    continue;
                }
                $counters['valid']++;
                try {
                    $outcome = $service->importRow($check['record'], $batch, $counters);
                } catch (\Throwable $e) {
                    // One bad row never aborts the run; it is rejected with
                    // its reason while the batch continues (and stays
                    // complete/partial rather than failed).
                    $errors[] = "line {$row['line']}: {$e->getMessage()}";
                    $counters['rejected']++;
                    continue;
                }
                if ($outcome === 'unmatched') {
                    $counters['unmatched']++;
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
            // Validation rejects are counted, not fatal: the file was fully
            // processed. PARTIAL means a fatal error stopped processing
            // mid-file (committed rows stay; absence never deprecates).
            $processed = $counters['seen'];
            $batch->update([
                'status' => $fatal !== null
                    ? ($processed > 0 ? DgdaImportBatch::STATUS_PARTIAL : DgdaImportBatch::STATUS_FAILED)
                    : DgdaImportBatch::STATUS_COMPLETE,
                'records_seen' => $counters['seen'],
                'records_valid' => $counters['valid'],
                'records_created' => $counters['created'],
                'records_updated' => $counters['updated'],
                'records_unchanged' => $counters['unchanged'],
                'records_rejected' => $counters['rejected'],
                'records_unmatched' => $counters['unmatched'],
                'records_ambiguous' => $counters['ambiguous'],
                'error_summary' => $fatal ?? (implode("\n", array_slice($errors, 0, 20)) ?: null),
                'completed_at' => now(),
            ]);
        }

        $this->table(['metric', 'count'], [
            ['records seen', $counters['seen']],
            ['valid', $counters['valid']],
            ['created', $counters['created']],
            ['updated', $counters['updated']],
            ['unchanged', $counters['unchanged']],
            ['rejected', $counters['rejected']],
            ['unmatched', $counters['unmatched']],
            ['ambiguous', $counters['ambiguous']],
        ]);
        if ($fatal !== null) {
            $this->error('FAILED: '.$fatal);
        } elseif ($dryRun) {
            // The pending batch row itself is removed: dry-run leaves zero trace.
            $batchId = $batch->id;
            $batch->delete();
            $this->info("[dry-run] no rows persisted (attempt #{$batchId} discarded).");
        } else {
            $this->info("Batch #{$batch->id} {$batch->fresh()->status}.");
        }

        return $fatal !== null ? self::FAILURE : self::SUCCESS;
    }
}
