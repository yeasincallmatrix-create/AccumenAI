<?php

namespace App\Console\Commands;

use App\Services\System\ProductionDatabaseAuditService;
use Illuminate\Console\Command;

class DatabaseProductionAudit extends Command
{
    protected $signature = 'database:production-audit {--json}';

    protected $description = 'Step 110 — Final Database Production Audit (score 0-100)';

    public function handle(ProductionDatabaseAuditService $service): int
    {
        $result = $service->audit();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info("DATABASE READY SCORE");
        $this->line(str_repeat("=", 40));
        foreach ($result['scores'] as $k => $v) {
            $label = ucfirst($k);
            $color = $v >= 90 ? 'info' : ($v >= 70 ? 'warn' : 'error');
            $this->$color(sprintf("%-15s %3d%%", $label.":", $v));
        }
        $this->line(str_repeat("-", 40));
        $overall = $result['overall'];
        $status = $result['status'];
        $method = $overall >= 90 ? 'info' : ($overall >= 70 ? 'warn' : 'error');
        $this->$method("Overall: $overall/100 — $status");

        $this->newLine();
        $checks = $result['checks'];
        $this->line("Checks:");
        $this->line("  Migrations: ".($checks['migrations']['healthy'] ? 'OK' : count($checks['migrations']['pending']).' pending'));
        $this->line("  Missing tables: ".(empty($checks['missing_tables']['missing']) ? '0' : count($checks['missing_tables']['missing'])));
        $this->line("  Seeds: ".($checks['seeds']['healthy'] ? 'OK' : implode(', ', $checks['seeds']['missing'])));
        $this->line("  Indexes missing: ".count($checks['indexes']['missing']));
        $this->line("  Backups: {$checks['backups']['count']} (latest: ".($checks['backups']['latest']->status ?? 'none').")");
        $this->line("  Orphans: ".count($checks['orphans']['orphans'] ?? []));

        return $overall >= 70 ? 0 : 1;
    }
}
