<?php

namespace App\Console\Commands;

use App\Services\System\TenantIsolationAuditService;
use Illuminate\Console\Command;

class SystemTenantIsolationAudit extends Command
{
    protected $signature = 'system:tenant-isolation-audit {--json}';
    protected $description = 'Step 115 — Tenant Isolation Deep Audit';

    public function handle(TenantIsolationAuditService $service): int
    {
        $res = $service->audit();
        if ($this->option('json')) {
            $this->line(json_encode($res, JSON_PRETTY_PRINT));
            return 0;
        }
        $this->line($service->report());
        return $res['status'] === 'SECURE' ? 0 : 1;
    }
}
