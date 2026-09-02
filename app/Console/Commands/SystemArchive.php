<?php

namespace App\Console\Commands;

use App\Services\System\ArchiveService;
use Illuminate\Console\Command;

class SystemArchive extends Command
{
    protected $signature = 'system:archive {module? : attendance|notifications|audit_logs|activity_logs} {--dry-run}';

    protected $description = 'Step 107 — Archive old records (never auto-delete)';

    public function handle(ArchiveService $service): int
    {
        $module = $this->argument('module');

        if ($module) {
            $result = $service->archive($module, (bool)$this->option('dry-run'));
            $this->info("Module: {$result['module']} | Cutoff: {$result['cutoff']} | Eligible: {$result['total']} | Archived: {$result['archived']}" . ($result['dry_run'] ? ' (dry-run)' : ''));
            return 0;
        }

        foreach (array_keys(ArchiveService::RULES) as $mod) {
            $result = $service->archive($mod, (bool)$this->option('dry-run'));
            $this->line("Module: {$result['module']} | Eligible: {$result['total']} | Archived: {$result['archived']}");
        }

        $this->info("Done. Originals kept — deletion requires manual approval.");

        return 0;
    }
}
