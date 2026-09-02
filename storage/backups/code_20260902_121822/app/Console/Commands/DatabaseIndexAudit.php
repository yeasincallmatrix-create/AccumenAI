<?php

namespace App\Console\Commands;

use App\Services\System\DatabaseIndexAuditService;
use Illuminate\Console\Command;

class DatabaseIndexAudit extends Command
{
    protected $signature = 'database:index-audit {--json}';

    protected $description = 'Step 105 — Database Index Audit (missing, duplicate, unused, FK indexes)';

    public function handle(DatabaseIndexAuditService $service): int
    {
        $audit = $service->audit();

        if ($this->option('json')) {
            $this->line(json_encode($audit, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info($service->generateReport());

        if ($audit['total_missing'] > 0) {
            $this->warn("Missing indexes: {$audit['total_missing']} — recommendation only");
        } else {
            $this->info("All critical indexes present");
        }

        return 0;
    }
}
