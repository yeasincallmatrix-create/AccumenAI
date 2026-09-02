<?php

namespace App\Console\Commands;

use App\Services\System\EnterpriseDatabaseCertificationService;
use Illuminate\Console\Command;

class DatabaseCertify extends Command
{
    protected $signature = 'database:certify {--json}';

    protected $description = 'Step 120 — Final Enterprise Database Certification';

    public function handle(EnterpriseDatabaseCertificationService $service): int
    {
        $res = $service->certify();

        if ($this->option('json')) {
            $this->line(json_encode($res, JSON_PRETTY_PRINT));
            return $res['status'] === 'CERTIFIED' ? 0 : 1;
        }

        $this->info("Accumen AI SaaS");
        $this->info("ENTERPRISE DATABASE CERTIFICATION");
        $this->line(str_repeat("=", 40));
        foreach ($res['scores'] as $k => $v) {
            $method = $v >= 90 ? 'info' : ($v >= 70 ? 'warn' : 'error');
            $this->$method(sprintf("%-15s %3d/100", $k.":", $v));
        }
        $this->line(str_repeat("-", 40));
        $this->info("OVERALL: {$res['overall']}/100");
        $this->info("Status: {$res['status']}");

        return $res['status'] === 'NOT CERTIFIED' ? 1 : 0;
    }
}
