<?php

namespace App\Console\Commands;

use App\Services\System\DatabaseDuplicateAuditService;
use Illuminate\Console\Command;

class DatabaseDuplicateAudit extends Command
{
    protected $signature = 'database:duplicate-audit {--json}';
    protected $description = 'Step 114 — Duplicate Data Audit';

    public function handle(DatabaseDuplicateAuditService $service): int
    {
        $res = $service->audit();
        if ($this->option('json')) {
            $this->line(json_encode($res, JSON_PRETTY_PRINT));
            return 0;
        }
        $this->line($service->report());
        return 0;
    }
}
