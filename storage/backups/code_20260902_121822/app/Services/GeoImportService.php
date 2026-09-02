<?php

namespace App\Services;

use App\Geo\Contracts\GeoDataProvider;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\GeoImport;
use Illuminate\Support\Facades\DB;

/**
 * Reusable country-by-country geography importer.
 *
 * Turns a GeoDataProvider (local package today, external API tomorrow) into
 * administrative_units records. Shared by the `geo:import-package` CLI command,
 * the Super Admin upload UI and tests.
 *
 * Guarantees:
 *  - streaming: records are consumed via the provider generator; a package is
 *    never fully loaded into PHP memory;
 *  - chunked: a configurable number of records is flushed per DB transaction;
 *  - upsert: records match on the (country_id, code) natural key — a re-import
 *    updates rather than duplicates;
 *  - safe: a failing chunk rolls back; earlier chunks stay; nothing is deleted;
 *  - duplicate-safe within the file and across runs;
 *  - resumable: runBatch() consumes only the next chunk so admin UI polls can
 *    continue a large import without long-running HTTP requests.
 */
class GeoImportService
{
    public function __construct(
        private int $chunkSize = 1000,
        private int $recordsPerRequest = 2000,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('geo.import.chunk_size', 1000),
            (int) config('geo.import.records_per_request', 2000),
        );
    }

    /** Full import (CLI / small uploads). */
    public function import(GeoDataProvider $provider, Country $country): array
    {
        return $this->run($provider, $country, write: true);
    }

    /** Validate the whole package without writing. */
    public function validate(GeoDataProvider $provider, Country $country): array
    {
        return $this->run($provider, $country, write: false);
    }

    /**
     * Resume a batched import: stream the next $limit records, persist the
     * accumulated progress on the GeoImport row, and return the updated report.
     * The caller must construct the provider with startLine = import->total_records.
     */
    public function runBatch(GeoImport $import, GeoDataProvider $provider, int $limit): array
    {
        $report = $this->run($provider, $import->country, write: true, maxRecords: $limit);

        $import->forceFill([
            'total_records' => (int) $import->total_records + $report['total'],
            'inserted_records' => (int) $import->inserted_records + $report['inserted'],
            'updated_records' => (int) $import->updated_records + $report['updated'],
            'skipped_records' => (int) $import->skipped_records + $report['skipped'],
            'duplicate_count' => (int) $import->duplicate_count + $report['duplicates'],
            'error_count' => (int) $import->error_count + $report['errors'],
            'error_summary' => $report['error_summary'] ?? $import->error_summary,
            'status' => $report['status'],
            'started_at' => $import->started_at ?? now(),
            'completed_at' => in_array($report['status'], ['completed', 'failed'], true) ? now() : null,
        ])->save();

        $report['import_id'] = $import->id;

        return $report;
    }

    /** Core engine. */
    private function run(GeoDataProvider $provider, Country $country, bool $write, ?int $maxRecords = null): array
    {
        $report = [
            'country' => $country->name,
            'country_iso2' => $country->iso2,
            'total' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'error_summary' => null,
            'finished' => true,
            'status' => $write ? 'importing' : 'validating',
            'error_rows' => [],
        ];

        if ($write) {
            $this->ensureLevels($country);
            $levelByNumber = $this->levelIdsByNumber($country);
        } else {
            $levelByNumber = $this->levelIdsByNumber($country);
        }

        if ($levelByNumber === []) {
            $report['errors'] = 1;
            $report['error_summary'] = "No administrative levels configured for {$country->name} ({$country->iso2}). Configure them first (admin → Locations).";
            $report['finished'] = false;
            $report['status'] = 'failed';

            return $report;
        }

        $unitByCode = [];   // "countryId:code" => unit id seen this run
        $chunk = [];
        $panicked = false;

        /** Flush a collected chunk (bounded transaction). */
        $flush = function () use (&$chunk, &$report, &$panicked, $country, $write) {
            if ($chunk === []) {
                return;
            }

            $perform = function () use (&$chunk, $country): array {
                $outcome = $this->upsertLogicalChunk($chunk, $country);

                return [
                    'inserted' => $outcome['inserted'],
                    'updated' => $outcome['updated'],
                    'duplicates' => $outcome['duplicates'],
                    'errors' => $outcome['errors'],
                    'error_messages' => $outcome['error_messages'],
                ];
            };

            if ($write) {
                try {
                    $outcome = DB::transaction($perform);
                } catch (\Throwable $e) {
                    // Chunk-level transaction rolled back: prior chunks survive.
                    $report['errors'] += count($chunk);
                    if ($report['error_summary'] === null) {
                        $report['error_summary'] = 'Import failed mid-chunk: '.$e->getMessage().' Earlier chunks were preserved.';
                    }
                    $chunk = [];
                    $report['finished'] = false;
                    $panicked = true;

                    return;
                }
            } else {
                // Validate-only: exercise the exact same upsert logic inside a
                // transaction that is rolled back, so nothing persists but
                // DB-level surprises (e.g. constraint violations) still surface
                // as row errors.
                DB::beginTransaction();
                try {
                    $outcome = $perform();
                } finally {
                    DB::rollBack();
                }
            }

            $report['inserted'] += $outcome['inserted'];
            $report['updated'] += $outcome['updated'];
            $report['duplicates'] += $outcome['duplicates'];
            $report['errors'] += $outcome['errors'];

            foreach ($outcome['error_messages'] as $message) {
                $report['error_rows'][] = $message;
            }
            if ($report['errors'] > 0 && $report['error_summary'] === null) {
                $report['error_summary'] = ($write
                        ? 'Row-level failures during import'
                        : 'Row-level validation failures').': '
                    .implode('; ', array_slice($outcome['error_messages'], 0, 3));
            }

            $chunk = [];
        };

        foreach ($provider->records() as $record) {
            if ($maxRecords !== null && $report['total'] >= $maxRecords) {
                $report['finished'] = false;
                break;
            }

            $report['total']++;

            $level = (int) $record['level'];
            if (! isset($levelByNumber[$level])) {
                $report['skipped']++;
                $report['errors']++;
                $report['error_summary'] ??= "Country has no level {$level} configured.";

                continue;
            }

            $code = trim((string) $record['code']);
            $name = trim((string) $record['name']);
            if ($code === '' || $name === '') {
                $report['skipped']++;
                $report['errors']++;

                continue;
            }

            $key = $country->id.':'.$code;

            // In-file duplicate (spans chunks within this run).
            if (isset($unitByCode[$key])) {
                $report['duplicates']++;
                $report['skipped']++;

                continue;
            }
            $unitByCode[$key] = true;

            $parentCode = $record['parent_code'] ?? null;

            if ($level > 1) {
                if ($parentCode === null || trim((string) $parentCode) === '') {
                    $report['skipped']++;
                    $report['errors']++;
                    $report['error_summary'] ??= "Level-{$level} record {$code} has no parent.";

                    continue;
                }
                // Parent id resolution happens at upsert time (inside the chunk
                // transaction) so parents earlier in the same chunk / file are
                // visible. Here we only require the parent code to be present.
                $parentCode = trim((string) $parentCode);
            }

            $chunk[] = [
                'level' => $level,
                'code' => $code,
                'name' => $name,
                'parent_code' => $parentCode,
                'postal_code' => $record['postal_code'],
                'latitude' => $record['latitude'],
                'longitude' => $record['longitude'],
                'level_id' => $levelByNumber[$level],
            ];

            if (count($chunk) >= $this->chunkSize) {
                $flush();
            }
        }

        $flush();

        // finished = the stream was fully consumed without a chunk panic.
        // Row-level errors are skipped records, not fatal.
        $report['finished'] = $report['finished'] && ! $panicked;

        if ($panicked) {
            $report['status'] = 'failed';
        } elseif (! $report['finished']) {
            $report['status'] = $write ? 'importing' : 'validating';
        } elseif ($report['errors'] > 0 && $write) {
            $report['status'] = 'completed';
        } elseif ($report['errors'] > 0) {
            $report['status'] = 'validated';
        } else {
            $report['status'] = $write ? 'completed' : 'validated';
        }

        return $report;
    }

    private function levelIdsByNumber(Country $country): array
    {
        return AdministrativeLevel::query()
            ->where('country_id', $country->id)
            ->get(['level_number', 'id'])
            ->pluck('id', 'level_number')
            ->all();
    }

    /**
     * Create missing administrative-level definitions for a country before an
     * import. Existing rows are reused untouched; new ones are created from the
     * curated config labels so a brand-new country can be imported right away.
     */
    private function ensureLevels(Country $country): void
    {
        $labels = config('geo-labels.labels.'.$country->iso2) ?? config('geo-labels.defaults', []);
        $existing = AdministrativeLevel::query()
            ->where('country_id', $country->id)
            ->pluck('id', 'level_number')
            ->all();

        foreach ([1, 2, 3] as $number) {
            if (isset($existing[$number])) {
                continue;
            }
            AdministrativeLevel::create([
                'country_id' => $country->id,
                'level_number' => $number,
                'name' => $labels[$number] ?? 'Level '.$number,
                'slug' => strtolower($country->iso2).'_level_'.$number,
                'status' => true,
            ]);
        }
    }

    /**
     * Insert/update a chunk of records inside the caller's transaction.
     *
     * Classifies each row by asking the DB directly, which keeps counts honest
     * even for resumable runs. Parents are resolved here — inside the chunk
     * transaction — so a parent created earlier in the same chunk (or present
     * from a previous run) is linked correctly.
     */
    private function upsertLogicalChunk(array $chunk, Country $country): array
    {
        $inserted = 0;
        $updated = 0;
        $duplicates = 0;
        $errors = 0;
        $errorMessages = [];
        $seen = [];
        $idByCode = [];   // "countryId:code" => unit id known in this chunk/run

        // Pre-seed ids for existing records referenced as parents so children
        // resolve without ordering concerns inside the same transaction.
        foreach ($chunk as $record) {
            if ($record['level'] > 1 && ! empty($record['parent_code'])) {
                $parentKey = $country->id.':'.$record['parent_code'];
                if (! isset($idByCode[$parentKey])) {
                    $id = $this->unitIdByCode($country, $record['parent_code']);
                    if ($id !== null) {
                        $idByCode[$parentKey] = (int) $id;
                    }
                }
            }
        }

        foreach ($chunk as $record) {
            $key = $country->id.':'.$record['code'];
            if (isset($seen[$key])) {
                $duplicates++;

                continue;
            }
            $seen[$key] = true;

            if ($record['level'] > 1) {
                $parentKey = $country->id.':'.$record['parent_code'];
                $parentId = $idByCode[$parentKey] ?? null;
                if ($parentId === null) {
                    $errors++;
                    $errorMessages[] = $record['code'].': parent "'.$record['parent_code'].'" not found at level '.($record['level'] - 1);

                    continue;
                }
            } else {
                $parentId = null;
            }

            $attributes = [
                'country_id' => $country->id,
                'administrative_level_id' => $record['level_id'],
                'parent_id' => $parentId,
                'name' => $record['name'],
                'code' => $record['code'],
                'postal_code' => $record['postal_code'],
                'latitude' => $record['latitude'],
                'longitude' => $record['longitude'],
                'status' => true,
            ];

            try {
                $row = AdministrativeUnit::query()
                    ->where('country_id', $country->id)
                    ->where('code', $record['code'])
                    ->first();

                if ($row !== null) {
                    $row->update($attributes);
                    $updated++;
                    $idByCode[$key] = (int) $row->id;
                } else {
                    $created = AdministrativeUnit::create($attributes);
                    $inserted++;
                    $idByCode[$key] = (int) $created->id;
                }
            } catch (\Throwable $e) {
                $errors++;
                $errorMessages[] = $record['code'].': '.$e->getMessage();
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'duplicates' => $duplicates,
            'errors' => $errors,
            'error_messages' => $errorMessages,
        ];
    }

    private function unitIdByCode(Country $country, string $code): ?int
    {
        return AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->where('code', $code)
            ->value('id');
    }
}
