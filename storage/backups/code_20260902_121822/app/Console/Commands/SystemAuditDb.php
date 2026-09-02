<?php

namespace App\Console\Commands;

use App\Services\System\DatabaseHealthCheckService;
use Illuminate\Console\Command;

class SystemAuditDb extends Command
{
    protected $signature = 'system:audit-db {--json : Output as JSON} {--no-persist : Do not persist audit}';

    protected $description = 'Step 101 — Database Health Audit: migrations, tables, seeds, orphans, indexes, tenant isolation';

    public function handle(DatabaseHealthCheckService $health): int
    {
        $persist = ! $this->option('no-persist');
        $result = $health->run($persist);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $result['status'] === 'critical' ? 1 : 0;
        }

        $this->newLine();
        $this->info('=== DATABASE HEALTH AUDIT (Step 101) ===');
        $this->newLine();

        $statusColor = $result['status'] === 'healthy' ? 'info' : ($result['status'] === 'warning' ? 'warn' : 'error');
        $this->$statusColor("Status: {$result['status']} | Score: {$result['score']}/100");

        foreach ($result['checks'] as $key => $check) {
            $healthy = $check['healthy'] ?? false;
            $icon = $healthy ? '✓' : '✗';
            $method = $healthy ? 'info' : 'error';
            $details = $check['details'] ?? null;
            if (is_array($details)) $details = json_encode($details);
            $this->$method(" $icon $key: " . ($details ?? json_encode($check)));

            if (! $healthy) {
                if (! empty($check['pending'])) {
                    $this->line('    Pending: '.implode(', ', array_slice($check['pending'], 0, 5)).(count($check['pending'])>5?'...':''));
                }
                if (! empty($check['missing'])) {
                    $this->line('    Missing: '.implode(', ', array_slice($check['missing'], 0, 5)).(count($check['missing'])>5?'...':''));
                }
                if (! empty($check['orphans'])) {
                    $this->line('    Orphans: '.implode(', ', $check['orphans']));
                }
                if (! empty($check['issues'])) {
                    $this->line('    Issues: '.implode(', ', $check['issues']));
                }
            }
        }

        $this->newLine();
        if ($result['status'] === 'critical') {
            $this->error('CRITICAL — action required');
            return 1;
        }
        if ($result['status'] === 'warning') {
            $this->warn('WARNING — review recommended');
            return 0;
        }
        $this->info('HEALTHY — no action required');
        return 0;
    }
}
