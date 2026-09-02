<?php

namespace App\Console\Commands;

use App\Services\System\DataIntegrityService;
use Illuminate\Console\Command;

class SystemIntegrityCheck extends Command
{
    protected $signature = 'system:integrity-check {--json : Output as JSON}';

    protected $description = 'Step 102 — Database Integrity & Repair Assistant (tenant, relationships, soft-delete)';

    public function handle(DataIntegrityService $service): int
    {
        $result = $service->generateReport();

        if ($this->option('json')) {
            $this->line(json_encode($result['report'], JSON_PRETTY_PRINT));
            return 0;
        }

        foreach ($result['lines'] as $line) {
            if (str_contains($line, 'PASS')) {
                $this->info($line);
            } elseif (str_contains($line, 'WARNING') || str_contains($line, 'Orphans')) {
                $this->warn($line);
            } else {
                $this->line($line);
            }
        }

        return 0;
    }
}
