<?php

namespace App\Console\Commands;

use App\Services\System\AccountingIntegrityAuditService;
use Illuminate\Console\Command;

class AccountingIntegrityAudit extends Command
{
    protected $signature = 'accounting:integrity-audit {--json}';
    protected $description = 'Step 116 — Accounting Data Integrity Audit';

    public function handle(AccountingIntegrityAuditService $service): int
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
