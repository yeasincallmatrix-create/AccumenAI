<?php

namespace App\Console\Commands;

use App\Services\System\DisasterRecoveryService;
use Illuminate\Console\Command;

/**
 * Step 119 + 125-G — Disaster Recovery Test & Restore Drill Command.
 */
class BackupDisasterTest extends Command
{
    protected $signature = 'backup:disaster-test
        {--json : Output as JSON}
        {--drill : Run safe restore drill with isolated temp database}';

    protected $description = 'Disaster recovery test or safe restore drill (read-only)';

    public function handle(DisasterRecoveryService $service): int
    {
        $drill = $this->option('drill');
        $res = $drill ? $service->restoreDrill() : $service->run();

        if ($this->option('json')) {
            $this->line(json_encode($res, JSON_PRETTY_PRINT));
            $result = $res['result'] ?? 'FAILED';
            return str_contains($result, 'FAILED') ? 1 : 0;
        }

        $this->info($drill ? 'RESTORE DRILL' : 'DISASTER RECOVERY TEST');
        $this->line(str_repeat('=', 50));

        if (isset($res['steps'])) {
            foreach ($res['steps'] as $k => $v) {
                $method = $v === 'PASS' ? 'info' : ($v === 'WARNING' ? 'comment' : 'error');
                $this->$method(sprintf("  %-25s %s", ucfirst(str_replace('_', ' ', $k)) . ':', $v));
            }
        } else {
            foreach (['backup','checksum','tables','migrations','seeds','row_counts','schema','restore_simulation','restore_plan','backup_metadata'] as $k) {
                $v = $res[$k] ?? 'UNKNOWN';
                $method = $v === 'PASS' ? 'info' : 'error';
                $this->$method(sprintf("  %-25s %s", ucfirst(str_replace('_', ' ', $k)) . ':', $v));
            }
        }

        if (isset($res['total_seconds'])) {
            $this->line('');
            $this->line("  Total duration: {$res['total_seconds']}s");
        }

        if (isset($res['safety'])) {
            $this->line('');
            $this->line('  Safety confirmations:');
            $this->line("    Production DB: {$res['safety']['production_database']}");
            $this->line("    Temp DB only: {$res['safety']['temp_database_only']}");
            $this->line("    Production untouched: " . ($res['safety']['production_untouched'] ? 'YES' : 'NO'));
        }

        $this->line('');
        $this->info("Result: {$res['result']}");
        $result = $res['result'] ?? 'FAILED';
        return str_contains($result, 'FAILED') ? 1 : 0;
    }
}
