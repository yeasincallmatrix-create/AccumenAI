<?php

namespace App\Console\Commands;

use App\Services\System\RecoveryTimeService;
use Illuminate\Console\Command;

/**
 * Step 125-F — Recovery Time Status Command.
 */
class DatabaseRecoveryStatus extends Command
{
    protected $signature = 'database:recovery-status
        {--json : Output as JSON}';

    protected $description = 'Show recovery time objective (RTO) status and drill history (read-only)';

    public function handle(RecoveryTimeService $service): int
    {
        $status = $service->status();
        $jsonOutput = $this->option('json');

        if ($jsonOutput) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('RECOVERY TIME OBJECTIVE (RTO)');
        $this->line(str_repeat('=', 50));
        $this->line("Status:        {$status['status']}");
        $this->line("RTO Status:    {$status['rto_status']}");
        $this->line("Target RTO:    {$status['target_rto_minutes']} minutes");
        $this->line("Drill count:   {$status['drill_count']}");

        if ($status['drill_count'] > 0) {
            $this->line('');
            $this->line('Recovery Times:');
            $this->line("  Average: {$status['average_recovery_seconds']}s");
            $this->line("  Fastest: {$status['fastest_recovery_seconds']}s");
            $this->line("  Slowest: {$status['slowest_recovery_seconds']}s");

            if ($status['latest_recovery']) {
                $this->line('');
                $this->line('Latest Drill:');
                $this->line("  Date:     {$status['latest_recovery']['date']}");
                $this->line("  Duration: {$status['latest_recovery']['total_ms']}ms");
                $this->line("  Status:   {$status['latest_recovery']['status']}");
            }
        }

        $this->line('');
        return self::SUCCESS;
    }
}
