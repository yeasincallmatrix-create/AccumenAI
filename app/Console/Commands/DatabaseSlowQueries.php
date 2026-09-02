<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseSlowQueries extends Command
{
    protected $signature = 'database:slow-queries {--min-ms=500} {--json}';

    protected $description = 'Step 124-D — Slow Queries (read-only)';

    public function handle(): int
    {
        $min = (int)$this->option('min-ms');
        $threshold = config('database-monitoring.slow_query_ms', 500);
        $min = max($min, $threshold);

        $rows = DB::table('database_query_logs')->where('execution_time', '>=', $min)->orderByDesc('execution_time')->limit(20)->get();

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info("SLOW QUERIES (min {$min}ms)");
        foreach ($rows as $r) {
            $this->line(sprintf("[%s] %.2fms: %s", $r->created_at, $r->execution_time, substr($r->query, 0, 120)));
        }
        if ($rows->isEmpty()) $this->info("No slow queries");

        return 0;
    }
}
