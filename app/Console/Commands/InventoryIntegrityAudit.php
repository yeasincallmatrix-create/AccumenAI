<?php

namespace App\Console\Commands;

use App\Services\System\InventoryIntegrityAuditService;
use Illuminate\Console\Command;

class InventoryIntegrityAudit extends Command
{
    protected $signature = 'inventory:integrity-audit {--json}';
    protected $description = 'Step 117 — Inventory Data Integrity Audit';

    public function handle(InventoryIntegrityAuditService $service): int
    {
        $res = $service->audit();
        if ($this->option('json')) {
            $this->line(json_encode($res, JSON_PRETTY_PRINT));
            return $res['healthy'] ? 0 : 1;
        }
        $this->line($service->report());
        return $res['healthy'] ? 0 : 1;
    }
}
