<?php

namespace App\Console\Commands;

use App\Services\System\QueryFingerprintService;
use Illuminate\Console\Command;

class DatabaseQueryTop extends Command
{
    protected $signature = 'database:query-top {--by=count : count|duration} {--json}';

    protected $description = 'Step 124-D — Top Query Fingerprints (read-only)';

    public function handle(QueryFingerprintService $service): int
    {
        $by = $this->option('by') ?? 'count';
        $rows = $service->top(10, $by === 'duration' ? 'duration' : 'count');

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info("TOP QUERIES BY ".strtoupper($by));
        foreach ($rows as $r) {
            $this->line(sprintf("count:%d avg:%.2f max:%.2f | %s", $r->execution_count, $r->average_duration, $r->maximum_duration, substr($r->normalized_query, 0, 100)));
        }

        return 0;
    }
}
