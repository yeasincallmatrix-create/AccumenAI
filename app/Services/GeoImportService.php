<?php

namespace App\Services;

use App\Geo\Contracts\GeoDataProvider;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\GeoImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
     * Phase 2 preview: validate + read-only impact analysis.
     *
     * Compares the file against live data to forecast duplicates,
     * code/name collisions and hierarchy conflicts BEFORE anything is
     * written. Never writes; safe to call from the UI Validate step.
     *
     * Returns the validate report with a 'preview' key:
     *   db_existing, code_name_conflicts[], name_code_conflicts[],
     *   hierarchy_conflicts[], zero_update_warning, mass_insert_warning,
     *   requires_confirm.
     */
    public function preview(GeoDataProvider $provider, Country $country): array
    {
        $report = $this->validate($provider, $country);
        $report['preview'] = $this->analyze($provider, $country, $report);

        return $report;
    }

    /**
     * Read-only file-vs-database comparison. Detail lists are capped;
     * counts are exact.
     */
    public function analyze(GeoDataProvider $provider, Country $country, array $report): array
    {
        $preview = [
            'db_existing' => 0,
            'code_name_conflicts' => [],
            'code_name_conflict_count' => 0,
            'name_code_conflicts' => [],
            'name_code_conflict_count' => 0,
            'hierarchy_conflicts' => [],
            'hierarchy_conflict_count' => 0,
            'zero_update_warning' => false,
            'mass_insert_warning' => false,
            'requires_confirm' => false,
        ];

        $preview['db_existing'] = AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->count();

        // File maps (single streaming pass).
        $fileByCode = [];        // code => [level, name]
        $fileDistDiv = [];       // district name => [division names]
        $fileUpDist = [];        // upazila code => district code
        $fileDistByCode = [];    // district code => district name
        foreach ($provider->records() as $record) {
            $code = trim((string) ($record['code'] ?? ''));
            $name = trim((string) ($record['name'] ?? ''));
            $level = (int) ($record['level'] ?? 0);
            if ($code === '' || $name === '' || $level < 1 || $level > 3) {
                continue;
            }
            $fileByCode[$code] = ['level' => $level, 'name' => $name];
            if ($level === 2) {
                $fileDistByCode[$code] = $name;
            }
            if ($level === 3 && ! empty($record['parent_code'])) {
                $fileUpDist[$code] = trim((string) $record['parent_code']);
            }
        }
        // Resolve file district -> division names via file district codes' parents.
        $fileDivByCode = [];
        foreach ($provider->records() as $record) {
            if ((int) ($record['level'] ?? 0) === 1 && trim((string) ($record['code'] ?? '')) !== '') {
                $fileDivByCode[trim((string) $record['code'])] = trim((string) ($record['name'] ?? ''));
            }
        }
        $fileDistDiv = [];
        foreach ($provider->records() as $record) {
            if ((int) ($record['level'] ?? 0) !== 2) {
                continue;
            }
            $dcode = trim((string) ($record['code'] ?? ''));
            $pcode = trim((string) ($record['parent_code'] ?? ''));
            if ($dcode === '') {
                continue;
            }
            $fileDistDiv[trim((string) ($record['name'] ?? ''))][] = $fileDivByCode[$pcode] ?? ('code:'.$pcode);
        }

        if ($fileByCode === []) {
            return $preview;
        }

        // DB maps (single load; geo tables are small).
        $units = AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->get(['id', 'code', 'name', 'parent_id', 'administrative_level_id']);
        $dbByCode = [];
        foreach ($units as $unit) {
            if ($unit->code === null || trim((string) $unit->code) === '') {
                continue;
            }
            $dbByCode[trim((string) $unit->code)] = ['name' => (string) $unit->name, 'level_id' => $unit->administrative_level_id];
        }
        $levelIdByNumber = $this->levelIdsByNumber($country);
        $levelNumberById = array_flip($levelIdByNumber);
        // DB district -> division names.
        $dbById = $units->keyBy('id');
        $dbDistDiv = [];
        foreach ($units as $unit) {
            $lvl = $levelNumberById[$unit->administrative_level_id] ?? 0;
            if ($lvl !== 2) {
                continue;
            }
            $parent = $dbById->get($unit->parent_id);
            $dbDistDiv[trim((string) $unit->name)][] = $parent ? trim((string) $parent->name) : '(no parent)';
        }

        // 1. Same code, different name (update would rename / was a mismatch).
        foreach ($fileByCode as $code => $info) {
            if (! isset($dbByCode[$code])) {
                continue;
            }
            if (mb_strtolower(trim($dbByCode[$code]['name'])) !== mb_strtolower(trim($info['name']))) {
                $preview['code_name_conflict_count']++;
                if (count($preview['code_name_conflicts']) < 20) {
                    $preview['code_name_conflicts'][] = [
                        'code' => $code,
                        'file_name' => $info['name'],
                        'db_name' => $dbByCode[$code]['name'],
                    ];
                }
            }
        }

        // 2. Same name (under the same parent) carrying a different code.
        // Parent-scoped so legitimate repeats (e.g. Kaliganj upazila in four
        // districts) never flag: only identical placements with clashing
        // codes are reported.
        $filePlaced = [];   // level:name:parentname => [codes]
        $dbPlaced = [];
        // File placements: need division names per district code first.
        $fileDivNameByCode = [];
        foreach ($provider->records() as $record) {
            if ((int) ($record['level'] ?? 0) === 1) {
                $c = trim((string) ($record['code'] ?? ''));
                if ($c !== '') {
                    $fileDivNameByCode[$c] = trim((string) ($record['name'] ?? ''));
                }
            }
        }
        $fileDistNameByCode = [];
        foreach ($provider->records() as $record) {
            $lvl = (int) ($record['level'] ?? 0);
            $code = trim((string) ($record['code'] ?? ''));
            $name = trim((string) ($record['name'] ?? ''));
            if ($code === '' || $name === '' || $lvl < 1 || $lvl > 3) {
                continue;
            }
            $parentName = '';
            if ($lvl === 2) {
                $parentName = $fileDivNameByCode[trim((string) ($record['parent_code'] ?? ''))] ?? '';
            } elseif ($lvl === 3) {
                $pcode = trim((string) ($record['parent_code'] ?? ''));
                $parentName = $fileDistByCode[$pcode] ?? '';
            }
            $filePlaced[$lvl.':'.mb_strtolower($name).':'.mb_strtolower($parentName)][] = $code;
        }
        $dbNameById = [];
        foreach ($units as $unit) {
            $dbNameById[$unit->id] = trim((string) $unit->name);
        }
        foreach ($units as $unit) {
            $lvl = $levelNumberById[$unit->administrative_level_id] ?? 0;
            if ($lvl < 1 || $lvl > 3) {
                continue;
            }
            $parentName = ($lvl === 1) ? '' : ($dbNameById[$unit->parent_id] ?? '');
            $dbPlaced[$lvl.':'.mb_strtolower(trim((string) $unit->name)).':'.mb_strtolower($parentName)][] = $unit->code;
        }
        foreach ($filePlaced as $key => $codes) {
            if (! isset($dbPlaced[$key])) {
                continue;
            }
            $fileCodes = array_values(array_unique($codes));
            $dbCodes = array_values(array_unique(array_map(fn ($c) => $c === null ? '' : trim((string) $c), $dbPlaced[$key])));
            if (array_diff($fileCodes, $dbCodes) !== [] || array_diff($dbCodes, $fileCodes) !== []) {
                [$lvl, $name] = explode(':', $key, 3) + [null, null, null];
                $preview['name_code_conflict_count']++;
                if (count($preview['name_code_conflicts']) < 20) {
                    $preview['name_code_conflicts'][] = [
                        'level' => (int) $lvl,
                        'name' => $name,
                        'file_codes' => $fileCodes,
                        'db_codes' => $dbCodes,
                    ];
                }
            }
        }

        // 3a. District under 2+ divisions inside the file.
        foreach ($fileDistDiv as $dist => $divs) {
            $uniq = array_values(array_unique($divs));
            if (count($uniq) > 1) {
                $preview['hierarchy_conflict_count']++;
                if (count($preview['hierarchy_conflicts']) < 20) {
                    $preview['hierarchy_conflicts'][] = [
                        'type' => 'district_multi_division_in_file',
                        'district' => $dist,
                        'divisions' => $uniq,
                    ];
                }
            }
        }

        // 3b. File district->division disagrees with DB (Brahmanbaria signature).
        foreach ($fileDistDiv as $dist => $divs) {
            if (! isset($dbDistDiv[$dist])) {
                continue;
            }
            foreach (array_unique($divs) as $fileDiv) {
                foreach (array_unique($dbDistDiv[$dist]) as $dbDiv) {
                    if (mb_strtolower($fileDiv) !== mb_strtolower($dbDiv)) {
                        $preview['hierarchy_conflict_count']++;
                        if (count($preview['hierarchy_conflicts']) < 20) {
                            $preview['hierarchy_conflicts'][] = [
                                'type' => 'district_division_mismatch',
                                'district' => $dist,
                                'file_division' => $fileDiv,
                                'db_division' => $dbDiv,
                            ];
                        }
                    }
                }
            }
        }

        // 3c. Upazila code exists in DB under a different district.
        $dbDistById = [];
        $dbCodeToParent = [];
        foreach ($units as $unit) {
            $lvl = $levelNumberById[$unit->administrative_level_id] ?? 0;
            if ($lvl === 2) {
                $dbDistById[$unit->id] = trim((string) $unit->name);
            }
            if ($lvl === 3 && $unit->code !== null && trim((string) $unit->code) !== '') {
                $dbCodeToParent[trim((string) $unit->code)] = $unit->parent_id;
            }
        }
        foreach ($fileUpDist as $upCode => $fileDistCode) {
            if (! isset($dbCodeToParent[$upCode])) {
                continue;
            }
            $dbDistName = $dbDistById[$dbCodeToParent[$upCode]] ?? null;
            $fileDistName = $fileDistByCode[$fileDistCode] ?? ('code:'.$fileDistCode);
            if ($dbDistName !== null && mb_strtolower($dbDistName) !== mb_strtolower($fileDistName)) {
                $preview['hierarchy_conflict_count']++;
                if (count($preview['hierarchy_conflicts']) < 20) {
                    $preview['hierarchy_conflicts'][] = [
                        'type' => 'upazila_district_mismatch',
                        'upazila_code' => $upCode,
                        'file_district' => $fileDistName,
                        'db_district' => $dbDistName,
                    ];
                }
            }
        }

        $preview['zero_update_warning'] = ($report['updated'] ?? 0) === 0
            && ($report['total'] ?? 0) > 0
            && $preview['db_existing'] > 0;
        $preview['mass_insert_warning'] = ($report['inserted'] ?? 0) > 0 && $preview['db_existing'] > 0;
        $preview['requires_confirm'] = $preview['mass_insert_warning']
            || $preview['code_name_conflict_count'] > 0
            || $preview['hierarchy_conflict_count'] > 0;

        return $preview;
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

        // Phase 3: accumulate the rollback trail (inserted ids + first
        // pre-update snapshots) so this import can be undone later.
        $this->mergeSnapshot($import, $report['touched_inserted'] ?? [], $report['touched_updated'] ?? []);

        $report['import_id'] = $import->id;

        return $report;
    }

    /**
     * Undo a completed (or partially failed) import: delete rows it inserted
     * and restore rows it updated to their pre-import values.
     *
     * @return array{restored: int, deleted: int, skipped_missing: int}
     */
    public function rollback(GeoImport $import): array
    {
        $result = ['restored' => 0, 'deleted' => 0, 'skipped_missing' => 0];
        $trail = $this->readSnapshot($import);
        if ($trail === null) {
            throw new \RuntimeException('No rollback snapshot exists for this import.');
        }

        DB::transaction(function () use ($import, $trail, &$result) {
            foreach ($trail['updated'] ?? [] as $id => $before) {
                $row = AdministrativeUnit::query()
                    ->where('country_id', $import->country_id)
                    ->whereKey($id)
                    ->first();
                if ($row === null) {
                    $result['skipped_missing']++;
                    continue;
                }
                $row->update($before);
                $result['restored']++;
            }
            foreach ($trail['inserted'] ?? [] as $id) {
                $result['deleted'] += AdministrativeUnit::query()
                    ->where('country_id', $import->country_id)
                    ->whereKey($id)
                    ->delete();
            }
        });

        $import->forceFill(['status' => 'rolled_back', 'completed_at' => now()])->save();

        return $result;
    }

    /**
     * Danger-zone helper: delete every administrative unit of a country
     * (level 3 → 2 → 1 so restrictive FKs clear in order) and report what
     * referenced them. Related patient/institute address FKs null out via
     * their onDelete rules; anything stricter aborts the transaction.
     *
     * @return array{deleted: array<int,int>, refs: array<string,int>}
     */
    public function clearCountry(Country $country): array
    {
        $result = ['deleted' => [], 'refs' => []];
        $ids = AdministrativeUnit::query()->where('country_id', $country->id)->pluck('id')->all();

        $result['refs'] = [
            'patients.present_admin_1_id' => DB::table('patients')->whereIn('present_admin_1_id', $ids)->count(),
            'patients.present_admin_2_id' => DB::table('patients')->whereIn('present_admin_2_id', $ids)->count(),
            'patients.present_admin_3_id' => DB::table('patients')->whereIn('present_admin_3_id', $ids)->count(),
            'institutes.admin_level_1_id' => DB::table('institutes')->whereIn('admin_level_1_id', $ids)->count(),
            'institutes.admin_level_2_id' => DB::table('institutes')->whereIn('admin_level_2_id', $ids)->count(),
            'institutes.admin_level_3_id' => DB::table('institutes')->whereIn('admin_level_3_id', $ids)->count(),
        ];

        DB::transaction(function () use ($country, &$result) {
            foreach ([3, 2, 1] as $level) {
                $levelId = AdministrativeLevel::query()
                    ->where('country_id', $country->id)
                    ->where('level_number', $level)
                    ->value('id');
                if ($levelId === null) {
                    $result['deleted'][$level] = 0;
                    continue;
                }
                $result['deleted'][$level] = AdministrativeUnit::query()
                    ->where('country_id', $country->id)
                    ->where('administrative_level_id', $levelId)
                    ->delete();
            }
        });

        return $result;
    }

    /**
     * Live unit counts per level plus reference counts (for the clear
     * preview shown before the type-to-confirm step).
     */
    public function clearPreview(Country $country): array
    {
        $units = AdministrativeUnit::query()->where('country_id', $country->id)->pluck('id')->all();
        $byLevel = AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->selectRaw('administrative_level_id, COUNT(*) c')
            ->groupBy('administrative_level_id')
            ->pluck('c', 'administrative_level_id')
            ->all();

        return [
            'country' => $country->name,
            'total' => count($units),
            'by_level_id' => $byLevel,
            'refs' => [
                'patients.present_admin_1_id' => DB::table('patients')->whereIn('present_admin_1_id', $units)->count(),
                'patients.present_admin_2_id' => DB::table('patients')->whereIn('present_admin_2_id', $units)->count(),
                'patients.present_admin_3_id' => DB::table('patients')->whereIn('present_admin_3_id', $units)->count(),
                'institutes.admin_level_1_id' => DB::table('institutes')->whereIn('admin_level_1_id', $units)->count(),
                'institutes.admin_level_2_id' => DB::table('institutes')->whereIn('admin_level_2_id', $units)->count(),
                'institutes.admin_level_3_id' => DB::table('institutes')->whereIn('admin_level_3_id', $units)->count(),
            ],
        ];
    }

    private function snapshotPath(GeoImport $import): string
    {
        return 'geo-snapshots/'.$import->id.'.json';
    }

    /** Merge one batch's trail into the import's snapshot file. */
    private function mergeSnapshot(GeoImport $import, array $inserted, array $updated): void
    {
        if ($inserted === [] && $updated === []) {
            return;
        }
        $trail = $this->readSnapshot($import) ?? ['inserted' => [], 'updated' => []];
        $trail['inserted'] = array_values(array_unique(array_merge($trail['inserted'], $inserted)));
        foreach ($updated as $id => $before) {
            if (! in_array($id, $trail['inserted'], true)) {
                $trail['updated'][$id] ??= $before;
            } else {
                unset($trail['updated'][$id]);
            }
        }
        Storage::disk('local')->put($this->snapshotPath($import), json_encode($trail));
    }

    private function readSnapshot(GeoImport $import): ?array
    {
        if (! Storage::disk('local')->exists($this->snapshotPath($import))) {
            return null;
        }
        $decoded = json_decode((string) Storage::disk('local')->get($this->snapshotPath($import)), true);

        return is_array($decoded) ? $decoded : null;
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
            'touched_inserted' => [],
            'touched_updated' => [],
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
                    'touched_inserted' => $outcome['touched_inserted'] ?? [],
                    'touched_updated' => $outcome['touched_updated'] ?? [],
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

            // Phase 3 trail: first-wins for updates (true pre-import state),
            // delete-wins for rows this run created.
            foreach ($outcome['touched_inserted'] ?? [] as $id) {
                $report['touched_inserted'][] = $id;
                unset($report['touched_updated'][$id]);
            }
            foreach ($outcome['touched_updated'] ?? [] as $id => $before) {
                if (! in_array($id, $report['touched_inserted'] ?? [], true)) {
                    $report['touched_updated'][$id] ??= $before;
                }
            }

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
        // Phase 3 rollback trail: inserted ids + pre-update snapshots.
        $touchedInserted = [];
        $touchedUpdated = [];
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
                    $tracked = ['country_id', 'administrative_level_id', 'parent_id', 'name', 'code', 'postal_code', 'latitude', 'longitude', 'status'];
                    $before = array_intersect_key($row->getAttributes(), array_flip($tracked));
                    // Snapshot only real changes; no-op rewrites need no undo.
                    $changed = false;
                    foreach ($tracked as $key) {
                        if ((string) ($before[$key] ?? '') !== (string) ($attributes[$key] ?? '')) {
                            $changed = true;
                            break;
                        }
                    }
                    if ($changed) {
                        $touchedUpdated[(int) $row->id] = $before;
                    }
                    $row->update($attributes);
                    $updated++;
                    $idByCode[$key] = (int) $row->id;
                } else {
                    $created = AdministrativeUnit::create($attributes);
                    $inserted++;
                    $idByCode[$key] = (int) $created->id;
                    $touchedInserted[] = (int) $created->id;
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
            'touched_inserted' => array_values(array_unique($touchedInserted)),
            'touched_updated' => $touchedUpdated,
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
