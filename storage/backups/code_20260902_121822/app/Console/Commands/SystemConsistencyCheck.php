<?php

namespace App\Console\Commands;

use App\Services\System\DatabaseConsistencyService;
use Illuminate\Console\Command;

class SystemConsistencyCheck extends Command
{
    protected $signature = 'system:consistency-check {--json}';

    protected $description = 'Step 112 — Database Consistency Audit (read-only)';

    public function handle(DatabaseConsistencyService $service): int
    {
        $report = $service->check();
        $text = $service->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->line($text);
        return 0;
    }
}
