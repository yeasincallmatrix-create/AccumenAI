<?php

namespace App\Console\Commands;

use App\Models\Medical\Medicine;
use App\Models\Medical\MedicineCodeHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateMedicineCodes extends Command
{
    protected $signature = 'medical:migrate-medicine-codes
                            {--institute= : Institute ID}
                            {--dry-run : Preview only}
                            {--force : Skip confirmation}';

    protected $description = 'Migrate legacy MED- codes to sequential numeric codes';

    public function handle(): int
    {
        $instituteId = (int) $this->option('institute');
        $dryRun = (bool) $this->option('dry-run');

        if (! $instituteId) {
            $this->error('--institute is required');

            return 1;
        }

        // Get all medicines with legacy MED- codes
        $legacy = Medicine::where('institute_id', $instituteId)
            ->whereNotNull('code')
            ->where('code', 'LIKE', 'MED-%')
            ->orderBy('id')
            ->get();

        if ($legacy->isEmpty()) {
            $this->info("No legacy codes found for institute {$instituteId}.");

            return 0;
        }

        $this->info("Found {$legacy->count()} legacy codes to migrate.");

        // Get all existing numeric codes
        $existingNumeric = Medicine::where('institute_id', $instituteId)
            ->whereNotNull('code')
            ->where('code', 'REGEXP', '^[0-9]+$')
            ->pluck('code')
            ->map(fn ($c) => (int) $c)
            ->toArray();

        $usedSet = array_flip($existingNumeric);
        $nextNum = 1000;

        // Build mapping
        $mapping = [];
        foreach ($legacy as $med) {
            while (isset($usedSet[$nextNum]) && $nextNum <= 999999) {
                $nextNum++;
            }
            if ($nextNum > 999999) {
                $this->error('Code capacity exhausted!');

                return 1;
            }

            $mapping[] = [
                'medicine_id' => $med->id,
                'old_code' => $med->code,
                'new_code' => (string) $nextNum,
            ];
            $usedSet[$nextNum] = true;
            $nextNum++;
        }

        // Show summary
        $this->table(
            ['Medicine ID', 'Old Code', 'New Code'],
            array_slice($mapping, 0, 10)
        );
        if (count($mapping) > 10) {
            $this->info('... and ' . (count($mapping) - 10) . ' more');
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no changes applied.');

            return 0;
        }

        if (! $this->option('force') && ! $this->confirm("Apply changes? This will update {$legacy->count()} codes.", true)) {
            return 0;
        }

        $migratedBy = auth()->id();

        // Apply in transaction
        DB::transaction(function () use ($instituteId, $mapping, $migratedBy) {
            foreach ($mapping as $row) {
                // Update medicine code (bypass model events that could
                // interfere — the code value is already reserved here).
                Medicine::where('id', $row['medicine_id'])
                    ->update(['code' => $row['new_code']]);

                // Record in history
                MedicineCodeHistory::create([
                    'institute_id' => $instituteId,
                    'medicine_id' => $row['medicine_id'],
                    'old_code' => $row['old_code'],
                    'new_code' => $row['new_code'],
                    'reason' => 'sequential_migration',
                    'migrated_at' => now(),
                    'migrated_by' => $migratedBy,
                ]);

                // Remap institute_medicines.local_code if exists
                if (Schema::hasTable('institute_medicines') && Schema::hasColumn('institute_medicines', 'local_code')) {
                    DB::table('institute_medicines')
                        ->where('institute_id', $instituteId)
                        ->where('local_code', $row['old_code'])
                        ->update(['local_code' => $row['new_code']]);
                }
            }
        });

        $this->info("Migration complete. {$legacy->count()} codes migrated.");

        return 0;
    }
}
