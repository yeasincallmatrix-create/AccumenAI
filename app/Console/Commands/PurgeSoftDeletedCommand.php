<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeSoftDeletedCommand extends Command
{
    protected $signature = 'backup:purge-soft-deleted {--days=30} {--dry-run}';

    protected $description = 'Permanently delete soft-deleted rows older than N days (30-day reversible window)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $tables = [];
        foreach (DB::select('SHOW TABLES') as $row) {
            $name = array_values((array) $row)[0];
            if (Schema::hasColumn($name, 'deleted_at')) {
                $tables[] = $name;
            }
        }

        $total = 0;

        foreach ($tables as $table) {
            $count = DB::table($table)
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', $cutoff)
                ->count();

            if ($count === 0) {
                continue;
            }

            if ($dryRun) {
                $this->line("[DRY] {$table}: {$count}");
            } else {
                DB::table($table)
                    ->whereNotNull('deleted_at')
                    ->where('deleted_at', '<', $cutoff)
                    ->delete();

                $this->info("{$table}: purged {$count}");
            }

            $total += $count;
        }

        $this->info(($dryRun ? 'Would purge' : 'Total purged') . ": {$total}");
        $this->line('Scanned ' . count($tables) . ' tables with deleted_at.');

        return self::SUCCESS;
    }
}
