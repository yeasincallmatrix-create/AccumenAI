<?php

namespace App\Console\Commands;

use App\Services\System\RestoreVerificationService;
use Illuminate\Console\Command;

class BackupVerify extends Command
{
    protected $signature = 'backup:verify {file : Path to backup file} {--json}';

    protected $description = 'Step 104 — Verify backup file via temporary import and health checks';

    public function handle(RestoreVerificationService $service): int
    {
        $file = $this->argument('file');

        // Resolve relative storage path
        if (! file_exists($file)) {
            $storagePath = storage_path('app/' . $file);
            if (file_exists($storagePath)) $file = $storagePath;
            else {
                $storagePath2 = storage_path('app/backups/' . basename($file));
                if (file_exists($storagePath2)) $file = $storagePath2;
            }
        }

        if (! file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $log = $service->verify($file);

        if ($this->option('json')) {
            $this->line(json_encode($log->report, JSON_PRETTY_PRINT));
            return $log->status === 'verified' ? 0 : 1;
        }

        $this->info("=== BACKUP VERIFICATION ===");
        $this->line("File: $file");
        $this->line("Status: {$log->status}");
        $this->line("Checksum: {$log->checksum}");
        $this->line("Tables: {$log->table_count}");
        $this->line("Rows: {$log->row_count}");

        $checks = $log->report['checks'] ?? [];
        foreach (['file_exists','migrations_match','seeds_healthy'] as $k) {
            $val = $checks[$k] ?? null;
            $icon = $val ? '✓' : '✗';
            $this->line(" $icon $k: ".json_encode($val));
        }

        if ($log->status === 'verified') {
            $this->info("VERIFIED");
            return 0;
        }
        $this->error("FAILED");
        return 1;
    }
}
