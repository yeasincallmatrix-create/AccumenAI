<?php

namespace App\Console\Commands;

use App\Support\CountryCodes;
use App\Support\PhoneNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PhoneNormalizeCommand extends Command
{
    protected $signature = 'phone:normalize {--dry-run : Report without modifying data}';
    protected $description = 'Normalize phone numbers across all phone columns with collision detection';

    /**
     * Map of tables/columns to scan.
     * Each entry: table, columns[], scope[] (unique constraint columns), countryResolver (closure or column name)
     */
    private function targets(): array
    {
        return [
            ['table' => 'institutes', 'columns' => ['phone', 'whatsapp'], 'scope' => null, 'country_col' => 'country'],
            ['table' => 'institute_users', 'columns' => ['phone'], 'scope' => ['global'], 'country_col' => null],
            ['table' => 'users', 'columns' => ['phone'], 'scope' => ['global'], 'country_col' => null],
            ['table' => 'platform_admins', 'columns' => ['phone'], 'scope' => ['global'], 'country_col' => null],
            ['table' => 'students', 'columns' => ['phone', 'guardian_phone', 'emergency_contact_phone'], 'scope' => ['institute_id'], 'country_col' => null],
            ['table' => 'crm_contacts', 'columns' => ['phone', 'phone_alt'], 'scope' => ['institute_id'], 'country_col' => null],
            ['table' => 'crm_leads', 'columns' => ['phone', 'phone_alt'], 'scope' => ['institute_id'], 'country_col' => null],
            ['table' => 'crm_organizations', 'columns' => ['phone'], 'scope' => ['institute_id'], 'country_col' => null],
            ['table' => 'parties', 'columns' => ['phone'], 'scope' => ['institute_id', 'branch_id', 'type'], 'country_col' => null],
            ['table' => 'guardians', 'columns' => ['phone'], 'scope' => ['institute_id'], 'country_col' => null],
            ['table' => 'teacher_profiles', 'columns' => ['emergency_contact_phone'], 'scope' => null, 'country_col' => null],
            ['table' => 'hr_employees', 'columns' => ['phone', 'emergency_contact_phone'], 'scope' => ['institute_id'], 'country_col' => null],
        ];
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $dbName = DB::connection()->getDatabaseName();
        $this->info("Database: {$dbName}");
        $this->info($isDryRun ? 'Mode: DRY-RUN (no data will be modified)' : 'Mode: LIVE (will update non-colliding rows)');

        // Verify backup existence (informational)
        $backupExists = file_exists(base_path('demo/monetix_backup_20260813.sql')) || DB::table('system_backups')->exists();
        if (! $backupExists) {
            $this->warn('No backup file detected at demo/monetix_backup_20260813.sql and no system_backups entry. Ensure a backup exists before live run.');
        }

        $totalScanned = 0;
        $totalEmpty = 0;
        $totalInvalid = 0;
        $totalValidNormalized = 0;
        $totalNational = 0;
        $totalInternational = 0;
        $totalFormatted = 0;
        $totalAmbiguous = 0;
        $totalToNormalize = 0;
        $totalCollisions = 0;
        $totalUpdated = 0;

        $allRows = []; // for collision detection: key = scopeKey|normalized => [records]
        $collisionDetails = [];
        $updates = []; // list of updates to apply

        // Preload institute country map for institute_id resolution
        $instituteCountryMap = DB::table('institutes')->pluck('country', 'id')->all();

        foreach ($this->targets() as $target) {
            $table = $target['table'];
            $columns = $target['columns'];
            $scope = $target['scope'];
            $countryCol = $target['country_col'];

            if (! Schema::hasTable($table)) {
                $this->warn("Table {$table} does not exist - skipped");
                continue;
            }

            // Filter columns that actually exist
            $existingCols = [];
            foreach ($columns as $col) {
                if (Schema::hasColumn($table, $col)) {
                    $existingCols[] = $col;
                } else {
                    $this->warn("Column {$table}.{$col} does not exist - skipped");
                }
            }
            if (empty($existingCols)) {
                continue;
            }

            $this->info("Scanning {$table} (" . implode(', ', $existingCols) . ") ...");

            // Build query: select id + columns + scope columns + institute_id for country
            $selectCols = ['id'];
            foreach ($existingCols as $c) { $selectCols[] = $c; }
            if ($scope) {
                foreach ($scope as $s) {
                    if ($s === 'global') continue;
                    if (! in_array($s, $selectCols) && Schema::hasColumn($table, $s)) {
                        $selectCols[] = $s;
                    }
                }
            }
            // Need institute_id for country resolution if not institutes table
            if ($table !== 'institutes' && Schema::hasColumn($table, 'institute_id') && ! in_array('institute_id', $selectCols)) {
                $selectCols[] = 'institute_id';
            }
            if ($countryCol && Schema::hasColumn($table, $countryCol) && ! in_array($countryCol, $selectCols)) {
                $selectCols[] = $countryCol;
            }

            $rows = DB::table($table)->select($selectCols)->get();

            foreach ($rows as $row) {
                foreach ($existingCols as $col) {
                    $totalScanned++;
                    $raw = $row->$col ?? null;

                    // Resolve country for this row
                    $country = null;
                    if ($countryCol && isset($row->$countryCol) && $row->$countryCol) {
                        $country = $row->$countryCol;
                    } elseif (isset($row->institute_id) && $row->institute_id && isset($instituteCountryMap[$row->institute_id])) {
                        $country = $instituteCountryMap[$row->institute_id];
                    }

                    $classification = PhoneNormalizer::classify($raw, $country);
                    $normalized = $raw !== null && trim((string)$raw) !== '' ? PhoneNormalizer::toE164((string)$raw, $country) : null;

                    switch ($classification) {
                        case 'EMPTY': $totalEmpty++; break;
                        case 'INVALID': $totalInvalid++; break;
                        case 'VALID_NORMALIZED': $totalValidNormalized++; break;
                        case 'NATIONAL_FORMAT': $totalNational++; break;
                        case 'INTERNATIONAL_FORMAT': $totalInternational++; break;
                        case 'FORMATTED': $totalFormatted++; break;
                        case 'AMBIGUOUS': $totalAmbiguous++; break;
                    }

                    if ($classification === 'EMPTY' || $classification === 'INVALID' || $classification === 'VALID_NORMALIZED') {
                        continue;
                    }

                    if ($normalized === null) {
                        $totalInvalid++;
                        continue;
                    }

                    // Check if already normalized (no change needed)
                    if ((string)$raw === $normalized) {
                        // Already valid but classified differently? count as valid
                        continue;
                    }

                    $totalToNormalize++;

                    // Build scope key for collision detection
                    $scopeKey = $table . '.' . $col;
                    if ($scope === null) {
                        $scopeKey .= '|no_scope';
                    } elseif (in_array('global', $scope)) {
                        $scopeKey .= '|global';
                    } else {
                        $parts = [];
                        foreach ($scope as $s) {
                            $parts[] = $s . '=' . (string)($row->$s ?? 'NULL');
                        }
                        $scopeKey .= '|' . implode(',', $parts);
                    }

                    $collisionKey = $scopeKey . '|norm=' . $normalized;
                    if (! isset($allRows[$collisionKey])) {
                        $allRows[$collisionKey] = [];
                    }
                    $allRows[$collisionKey][] = [
                        'table' => $table,
                        'column' => $col,
                        'id' => $row->id,
                        'raw' => $raw,
                        'normalized' => $normalized,
                        'country' => $country,
                        'scope' => $scope,
                        'scopeKey' => $scopeKey,
                    ];

                    $updates[] = [
                        'table' => $table,
                        'column' => $col,
                        'id' => $row->id,
                        'raw' => $raw,
                        'normalized' => $normalized,
                        'classification' => $classification,
                        'country' => $country,
                        'collisionKey' => $collisionKey,
                    ];
                }
            }
        }

        // Detect collisions: multiple records mapping to same normalized within same scope
        // Also detect if normalized already exists as current value in another row (even if that row not in to-normalize list)
        $collidingKeys = [];
        foreach ($allRows as $key => $records) {
            if (count($records) > 1) {
                $collidingKeys[$key] = $records;
                $totalCollisions += count($records);
            }
        }

        // Additional collision check: normalized value already exists in DB as current value (not being changed)
        foreach ($updates as $upd) {
            if (isset($collidingKeys[$upd['collisionKey']])) {
                continue; // already marked
            }
            $table = $upd['table'];
            $col = $upd['column'];
            $norm = $upd['normalized'];
            $id = $upd['id'];
            // Check if any other row already has this normalized value
            $q = DB::table($table)->where($col, $norm)->where('id', '!=', $id);
            // Apply scope filters for scoped uniqueness
            $target = collect($this->targets())->firstWhere('table', $table);
            $scope = $target['scope'] ?? null;
            if ($scope && ! in_array('global', $scope)) {
                $rowScope = DB::table($table)->where('id', $id)->first();
                foreach ($scope as $s) {
                    if ($rowScope && isset($rowScope->$s)) {
                        $q->where($s, $rowScope->$s);
                    }
                }
            }
            if ($q->exists()) {
                $conflicting = $q->first();
                $collidingKeys[$upd['collisionKey']] = [
                    $upd,
                    ['table'=>$table,'column'=>$col,'id'=>$conflicting->id,'raw'=>$conflicting->$col,'normalized'=>$norm,'collision_existing'=>true],
                ];
                $totalCollisions++;
            }
        }

        // Report
        $this->newLine();
        $this->info('=== PHONE NORMALIZATION REPORT ===');
        $this->line("Scanned: {$totalScanned}");
        $this->line("EMPTY: {$totalEmpty}");
        $this->line("INVALID: {$totalInvalid}");
        $this->line("VALID_NORMALIZED: {$totalValidNormalized}");
        $this->line("NATIONAL_FORMAT: {$totalNational}");
        $this->line("INTERNATIONAL_FORMAT: {$totalInternational}");
        $this->line("FORMATTED: {$totalFormatted}");
        $this->line("AMBIGUOUS: {$totalAmbiguous}");
        $this->line("To normalize: {$totalToNormalize}");
        $this->line("Collisions (records involved): {$totalCollisions}");
        $this->line("Collision groups: " . count($collidingKeys));

        if (! empty($collidingKeys)) {
            $this->newLine();
            $this->warn('COLLISION DETECTED - these records would normalize to same value within unique scope:');
            foreach ($collidingKeys as $key => $records) {
                $this->line("  Collision key: {$key}");
                foreach ($records as $r) {
                    $raw = $r['raw'] ?? 'NULL';
                    $id = $r['id'] ?? '?';
                    $tbl = $r['table'] ?? '?';
                    $norm = $r['normalized'] ?? '?';
                    $this->line("    - {$tbl} id={$id} raw='{$raw}' => '{$norm}'");
                }
            }
            $this->warn('These colliding records will NOT be modified. Manual review required.');
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('Dry-run complete. No data was modified.');
            if ($totalCollisions > 0) {
                $this->warn('Resolve collisions before live run. Non-colliding rows are safe to normalize.');
            } else {
                $this->info('No collisions detected. Live run would normalize ' . ($totalToNormalize - $totalCollisions) . ' records.');
            }
            // List sample of what would be normalized
            $this->newLine();
            $this->info('Sample of pending normalizations (max 20):');
            $shown = 0;
            foreach ($updates as $u) {
                if (isset($collidingKeys[$u['collisionKey']])) continue;
                $this->line("  {$u['table']}.{$u['column']} id={$u['id']} '{$u['raw']}' => '{$u['normalized']}' [{$u['classification']}]");
                if (++$shown >= 20) break;
            }
            return self::SUCCESS;
        }

        // LIVE run: apply non-colliding updates
        $toApply = array_filter($updates, fn($u) => ! isset($collidingKeys[$u['collisionKey']]));
        $this->info('Applying ' . count($toApply) . ' normalizations (skipping collisions)...');

        $applied = 0;
        foreach ($toApply as $u) {
            try {
                DB::table($u['table'])->where('id', $u['id'])->update([$u['column'] => $u['normalized']]);
                $applied++;
            } catch (\Exception $e) {
                $this->error("Failed to update {$u['table']}.{$u['column']} id={$u['id']}: " . $e->getMessage());
            }
        }

        $this->info("Updated {$applied} records.");
        if (! empty($collidingKeys)) {
            $this->warn(count($collidingKeys) . ' collision groups were skipped. Review and resolve manually.');
        }

        return self::SUCCESS;
    }
}
