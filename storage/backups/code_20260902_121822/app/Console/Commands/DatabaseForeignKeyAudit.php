<?php

namespace App\Console\Commands;

use App\Services\System\DatabaseForeignKeyAuditService;
use Illuminate\Console\Command;

class DatabaseForeignKeyAudit extends Command
{
    protected $signature = 'database:foreign-key-audit {--json}';
    protected $description = 'Step 113 — Foreign Key & Referential Integrity Hardening';

    public function handle(DatabaseForeignKeyAuditService $service): int
    {
        $audit = $service->audit();
        if ($this->option('json')) {
            $this->line(json_encode($audit, JSON_PRETTY_PRINT));
            return 0;
        }
        $this->line($service->report());
        return 0;
    }
}
