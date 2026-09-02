<?php

namespace App\Console\Commands;

use App\Services\System\SeedVersionService;
use Illuminate\Console\Command;

class SystemVerifySeeds extends Command
{
    protected $signature = 'system:verify-seeds {--json}';

    protected $description = 'Step 108 — Verify seed version control';

    public function handle(SeedVersionService $service): int
    {
        $result = $service->verifyAll();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $result['healthy'] ? 0 : 1;
        }

        $this->info("=== SEED VERSION VERIFICATION ===");
        foreach ($result['results'] as $name => $data) {
            $icon = $data['healthy'] ? '✓' : '✗';
            $method = $data['healthy'] ? 'info' : 'error';
            $this->$method(" $icon $name: " . ($data['healthy'] ? 'OK' : 'MISMATCH'));
            if (! $data['healthy']) {
                $this->line("   Current: {$data['current_checksum']} Stored: {$data['stored_checksum']}");
            }
        }

        if ($result['healthy']) {
            $this->info("All seeds verified");
            return 0;
        }
        $this->error("Seed verification failed: ".implode(', ', $result['missing']));
        return 1;
    }
}
