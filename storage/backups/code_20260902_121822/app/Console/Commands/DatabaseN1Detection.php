<?php

namespace App\Console\Commands;

use App\Services\System\N1DetectionService;
use Illuminate\Console\Command;

class DatabaseN1Detection extends Command
{
    protected $signature = 'database:n1-detection {--json}';

    protected $description = 'Step 124-E — N+1 Detection (CONFIRMED/SUSPECTED/REVIEW)';

    public function handle(N1DetectionService $service): int
    {
        $res = $service->detectEnhanced();

        if ($this->option('json')) {
            $this->line(json_encode($res, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info("N+1 DETECTION");
        foreach ($res['findings'] as $f) {
            $this->line(sprintf("[%s] %s (%s) - %s | %d queries | %s", $f['classification'], $f['model'], $f['type'], $f['query_pattern'], $f['query_count'], $f['recommendation']));
        }
        $this->line("Summary: CONFIRMED {$res['summary']['confirmed']}, SUSPECTED {$res['summary']['suspected']}, REVIEW {$res['summary']['review']}");

        return 0;
    }
}
